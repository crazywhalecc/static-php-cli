<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Package;

use Package\Target\php\unix;
use PHPUnit\Framework\TestCase;
use StaticPHP\Config\PackageConfig;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Runtime\SystemTarget;

/**
 * @internal
 */
class PhpUnixConfigureTest extends TestCase
{
    public function testConfigureLinksOnlyRuntimeLibrariesAndResolvedMacOSFrameworks(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Requires a Unix shell.');
        }

        $output = tempnam(sys_get_temp_dir(), 'spc-configure-libs-');
        $this->assertNotFalse($output);
        $property = new \ReflectionProperty(PackageConfig::class, 'package_configs');
        $savedConfig = $property->getValue();
        $env = [
            'SPC_TARGET' => '',
            'SPC_CMD_PREFIX_PHP_CONFIGURE' => escapeshellarg(PHP_BINARY) . ' -n -r ' . escapeshellarg('file_put_contents(' . var_export($output, true) . ', getenv("LIBS"));') . ' --',
            'SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS' => '',
            'SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS' => '',
            'SPC_EXTRA_PHP_VARS' => '',
            'LIBS' => '-lpgport',
        ];
        $savedEnv = [];
        foreach ($env as $name => $value) {
            $savedEnv[$name] = getenv($name);
            putenv("{$name}={$value}");
        }

        try {
            $property->setValue(null, [
                'krb5' => ['type' => 'library', 'frameworks' => ['Kerberos'], 'static-libs' => ['/fixtures/libkrb5.a']],
                'gettext' => ['type' => 'library', 'frameworks' => ['CoreFoundation', 'Kerberos']],
                'postgresql' => ['type' => 'library', 'static-libs' => ['/fixtures/libpgport.a']],
                'unresolved' => ['type' => 'library', 'frameworks' => ['Security']],
            ]);
            $package = $this->createMock(TargetPackage::class);
            $package->method('getSourceDir')->willReturn(__DIR__);
            $package->method('getIncludeDir')->willReturn('/fixtures/include');
            $package->method('getLibDir')->willReturn('/fixtures/lib');
            $package->method('getBuildOption')->willReturnCallback(static fn (string $name, mixed $default = null): mixed => $name === 'disable-opcache-jit' ? true : $default);
            $installer = $this->createMock(PackageInstaller::class);
            $resolved = [
                'krb5' => $this->createMock(LibraryPackage::class),
                'gettext' => $this->createMock(LibraryPackage::class),
                'postgresql' => $this->createMock(LibraryPackage::class),
            ];
            $installer->method('getResolvedPackages')->willReturnCallback(static function () use (&$resolved): array {
                return $resolved;
            });
            $php = new class {
                use unix;

                public static function getPHPVersionID(): int
                {
                    return 80500;
                }

                public function makeStaticExtensionString(PackageInstaller $installer): string
                {
                    return '';
                }
            };

            foreach (['x86_64-macos', 'aarch64-macos', 'x86_64-linux'] as $target) {
                putenv("SPC_TARGET={$target}");
                $php->configureForUnix($package, $installer);
                $expected = SystemTarget::getRuntimeLibs();
                if (str_contains($target, '-macos')) {
                    $expected .= ' -framework Kerberos -framework CoreFoundation';
                }
                $this->assertSame($expected, file_get_contents($output), $target);
            }

            putenv('SPC_TARGET=x86_64-macos');
            $resolved = ['postgresql' => $resolved['postgresql']];
            $php->configureForUnix($package, $installer);
            $this->assertSame(SystemTarget::getRuntimeLibs(), file_get_contents($output));
        } finally {
            unlink($output);
            $property->setValue(null, $savedConfig);
            foreach ($savedEnv as $name => $value) {
                $value === false ? putenv($name) : putenv("{$name}={$value}");
            }
        }
    }
}
