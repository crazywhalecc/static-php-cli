<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Util;

use PHPUnit\Framework\TestCase;
use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\Package;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Util\SPCConfigUtil;

/**
 * @internal
 */
class SPCConfigUtilTest extends TestCase
{
    /** @var array<string, false|string> */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        parent::setUp();
        ApplicationContext::reset();
        $this->resetPackageConfig();
        foreach (['CFLAGS', 'CXXFLAGS', 'LDFLAGS', 'LIBS', 'SPC_DEFAULT_CFLAGS', 'SPC_DEFAULT_CXXFLAGS', 'SPC_DEFAULT_LDFLAGS', 'SPC_EXTRA_LIBS'] as $name) {
            $this->savedEnv[$name] = getenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $name => $value) {
            $value === false ? putenv($name) : putenv("{$name}={$value}");
        }
        $this->resetPackageConfig();
        ApplicationContext::reset();
        parent::tearDown();
    }

    public function testStandaloneConfigStillControlsSuggestsDuringResolution(): void
    {
        $this->loadLinkFixture();
        $util = new SPCConfigUtil(['no_php' => true, 'libs_only_deps' => true]);

        $withoutSuggests = $util->config(['root']);
        $withSuggests = $util->config(['root'], include_suggests: true);

        $this->assertStringContainsString('/fixtures/librequired.a', $withoutSuggests['libs']);
        $this->assertStringNotContainsString('/fixtures/liboptional.a', $withoutSuggests['libs']);
        $this->assertStringContainsString('/fixtures/liboptional.a', $withSuggests['libs']);
    }

    public function testResolvedConfigIncludesOnlySuggestedPackagesThatWereResolved(): void
    {
        $this->loadLinkFixture();
        $util = new SPCConfigUtil(['no_php' => true, 'libs_only_deps' => true]);

        $withoutOptional = $util->configForResolvedBuild(['root'], $this->createInstaller([
            'required' => 'library',
            'root' => 'library',
        ]));
        $withOptional = $util->configForResolvedBuild(['root'], $this->createInstaller([
            'required' => 'library',
            'optional' => 'library',
            'root' => 'library',
        ]));

        $this->assertStringNotContainsString('/fixtures/liboptional.a', $withoutOptional['libs']);
        $this->assertStringContainsString('/fixtures/liboptional.a', $withOptional['libs']);
    }

    public function testResolvedConfigSupportsExplicitPhpLinkClosureOverrides(): void
    {
        $this->loadConfig([
            'php' => ['type' => 'target', 'depends' => ['core']],
            'core' => ['type' => 'library', 'static-libs' => ['/fixtures/libcore.a']],
            'ext-demo' => ['type' => 'php-extension', 'depends' => ['ext-lib']],
            'ext-lib' => ['type' => 'library', 'static-libs' => ['/fixtures/libext.a']],
            'php-fpm' => ['type' => 'virtual-target', 'depends' => ['php'], 'suggests' => ['fpm-lib']],
            'fpm-lib' => ['type' => 'library', 'static-libs' => ['/fixtures/libfpm.a']],
        ]);
        $util = new SPCConfigUtil(['libs_only_deps' => true]);

        $config = $util->configForResolvedBuild(['php'], $this->createInstaller([
            'core' => 'library',
            'ext-lib' => 'library',
            'ext-demo' => 'static-extension',
            'fpm-lib' => 'library',
            'php-fpm' => 'virtual-target',
            'php' => 'target',
        ]));

        $this->assertStringContainsString('/fixtures/libcore.a', $config['libs']);
        $this->assertStringContainsString('/fixtures/libext.a', $config['libs']);
        $this->assertStringContainsString('/fixtures/libfpm.a', $config['libs']);
    }

    public function testResolvedConfigRejectsRootOutsideResolvedSet(): void
    {
        $this->loadLinkFixture();

        $this->expectException(WrongUsageException::class);
        $this->expectExceptionMessage("Package 'root' is not part of the resolved package set");

        new SPCConfigUtil(['no_php' => true])->configForResolvedBuild(['root'], $this->createInstaller([
            'required' => 'library',
        ]));
    }

    public function testCAndCxxFlagsRemainIndependent(): void
    {
        putenv('SPC_DEFAULT_CFLAGS=-DDEFAULT_C');
        putenv('SPC_DEFAULT_CXXFLAGS=-DDEFAULT_CXX');
        putenv('SPC_DEFAULT_LDFLAGS=-Wl,default');
        putenv('CFLAGS=-DUSER_C');
        putenv('CXXFLAGS=-DUSER_CXX');
        putenv('LDFLAGS=-Wl,user');

        $config = new SPCConfigUtil(['no_php' => true, 'libs_only_deps' => true])->configWithResolvedPackages([]);

        $this->assertStringContainsString('-DDEFAULT_C', $config['cflags']);
        $this->assertStringContainsString('-DUSER_C', $config['cflags']);
        $this->assertStringNotContainsString('-DDEFAULT_CXX', $config['cflags']);
        $this->assertStringContainsString('-DDEFAULT_CXX', $config['cxxflags']);
        $this->assertStringContainsString('-DUSER_CXX', $config['cxxflags']);
        $this->assertStringContainsString('-Wl,default', $config['ldflags']);
        $this->assertStringContainsString('-Wl,user', $config['ldflags']);
    }

    private function loadLinkFixture(): void
    {
        $this->loadConfig([
            'root' => [
                'type' => 'library',
                'depends' => ['required'],
                'suggests' => ['optional'],
                'static-libs' => ['/fixtures/libroot.a'],
            ],
            'required' => ['type' => 'library', 'static-libs' => ['/fixtures/librequired.a']],
            'optional' => ['type' => 'library', 'static-libs' => ['/fixtures/liboptional.a']],
        ]);
    }

    private function loadConfig(array $configs): void
    {
        $reflection = new \ReflectionClass(PackageConfig::class);
        $property = $reflection->getProperty('package_configs');
        $property->setValue(null, $configs);
    }

    private function resetPackageConfig(): void
    {
        $this->loadConfig([]);
    }

    /**
     * @param array<string, 'library'|'static-extension'|'target'|'virtual-target'> $packages
     */
    private function createInstaller(array $packages): PackageInstaller
    {
        $installer = new PackageInstaller(['no-tracker' => true], false);
        $resolved = [];
        foreach ($packages as $name => $type) {
            $package = match ($type) {
                'static-extension' => $this->createStaticExtension($name),
                'target' => $this->createTarget($name, false),
                'virtual-target' => $this->createTarget($name, true),
                default => $this->createNamedPackage(LibraryPackage::class, $name),
            };
            $resolved[$name] = $package;
        }

        $reflection = new \ReflectionClass(PackageInstaller::class);
        $reflection->getProperty('packages')->setValue($installer, $resolved);
        return $installer;
    }

    private function createStaticExtension(string $name): PhpExtensionPackage
    {
        $extension = $this->createMock(PhpExtensionPackage::class);
        $extension->method('getName')->willReturn($name);
        $extension->method('isBuildStatic')->willReturn(true);
        return $extension;
    }

    private function createTarget(string $name, bool $virtual): TargetPackage
    {
        $target = $this->createMock(TargetPackage::class);
        $target->method('getName')->willReturn($name);
        $target->method('isVirtualTarget')->willReturn($virtual);
        return $target;
    }

    /** @param class-string<Package> $class */
    private function createNamedPackage(string $class, string $name): Package
    {
        $package = $this->createMock($class);
        $package->method('getName')->willReturn($name);
        return $package;
    }
}
