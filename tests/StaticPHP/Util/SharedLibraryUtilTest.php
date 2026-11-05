<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Util;

use PHPUnit\Framework\TestCase;
use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Toolchain\GccNativeToolchain;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\SharedLibraryUtil;
use StaticPHP\Util\SPCConfigUtil;

/** @internal */
final class SharedLibraryUtilTest extends TestCase
{
    private array $configs;

    private PackageBuilder $builder;

    private string $name;

    private array $files = [];

    protected function setUp(): void
    {
        if (\StaticPHP\Runtime\SystemTarget::getTargetOS() !== 'Linux') {
            self::markTestSkipped('Shared dependency libraries require Linux.');
        }
        $this->configs = (new \ReflectionProperty(PackageConfig::class, 'package_configs'))->getValue();
        $this->builder = ApplicationContext::get(PackageBuilder::class);
        $this->name = 'spc-shared-fixture-' . bin2hex(random_bytes(5));
        $config = ['type' => 'library', 'static-libs' => ['lib' . $this->name . '.a']];
        (new \ReflectionProperty(PackageConfig::class, 'package_configs'))->setValue(null, $this->configs + [$this->name => $config]);
        new PackageBuilder();
    }

    protected function tearDown(): void
    {
        SharedLibraryUtil::resetEnvironment();
        ApplicationContext::set(PackageBuilder::class, $this->builder);
        (new \ReflectionProperty(PackageConfig::class, 'package_configs'))->setValue(null, $this->configs);
        foreach ($this->files as $file) {
            unlink($file);
        }
    }

    public function testStaticIsTheDefaultEvenWhenSharedObjectsExist(): void
    {
        $this->file('.so');
        $package = new LibraryPackage($this->name, 'library');
        self::assertFalse(SharedLibraryUtil::enabled());
        self::assertFalse($package->isInstalled());
        $this->file('.a');
        self::assertTrue($package->isInstalled());
        self::assertSame(['lib' . $this->name . '.a'], SharedLibraryUtil::libraryFiles($this->name));
        self::assertSame('', SharedLibraryUtil::ldflags());
        self::assertSame([], SharedLibraryUtil::buildEnvironment());
    }

    public function testExplicitFalseKeepsArchiveChecksAndFlags(): void
    {
        new PackageBuilder(['with-shared-libs' => false]);
        $this->file('.a');
        self::assertTrue(new LibraryPackage($this->name, 'library')->isInstalled());
        self::assertFalse(SharedLibraryUtil::enabled());
        self::assertSame('', SharedLibraryUtil::ldflags());
    }

    public function testSharedModeRequiresTheDsoEvenWhenAnArchiveExists(): void
    {
        new PackageBuilder(['with-shared-libs' => true]);
        $package = new LibraryPackage($this->name, 'library');
        $this->file('.a');
        self::assertFalse($package->isInstalled());
        $this->file('.so');
        self::assertTrue($package->isInstalled());
        self::assertSame(['lib' . $this->name . '.so'], SharedLibraryUtil::libraryFiles($this->name));
        self::assertStringContainsString('-Wl,-rpath,/usr/lib/php-zts/lib', SharedLibraryUtil::ldflags());
    }

    public function testExplicitSharedNamesAndStaticOnlyExceptions(): void
    {
        $property = new \ReflectionProperty(PackageConfig::class, 'package_configs');
        $configs = $property->getValue();
        $configs[$this->name]['shared-libs'] = ['libspecial.so'];
        $property->setValue(null, $configs);
        new PackageBuilder(['with-shared-libs' => true]);
        self::assertSame(['libspecial.so'], SharedLibraryUtil::libraryFiles($this->name));
        $configs[$this->name]['static-only'] = true;
        $property->setValue(null, $configs);
        self::assertFalse(SharedLibraryUtil::buildsShared(new LibraryPackage($this->name, 'library')));
        self::assertSame(['lib' . $this->name . '.a'], SharedLibraryUtil::libraryFiles($this->name));
    }

    public function testPkgConfigModuleNamesFollowTheExplicitLinkageMode(): void
    {
        $property = new \ReflectionProperty(PackageConfig::class, 'package_configs');
        $configs = $property->getValue();
        $configs[$this->name] = ['type' => 'library', 'pkg-configs' => [$this->name . '-static'],
            'shared-pkg-configs' => [$this->name]];
        $property->setValue(null, $configs);
        FileSystem::createDir(BUILD_LIB_PATH . '/pkgconfig');
        $path = BUILD_LIB_PATH . '/pkgconfig/' . $this->name . '.pc';
        file_put_contents($path, 'fixture');
        $this->files[] = $path;
        $package = new LibraryPackage($this->name, 'library');
        self::assertSame([$this->name . '-static'], SharedLibraryUtil::pkgConfigFiles($this->name));
        self::assertFalse($package->isInstalled());
        new PackageBuilder(['with-shared-libs' => true]);
        self::assertSame([$this->name], SharedLibraryUtil::pkgConfigFiles($this->name));
        self::assertTrue($package->isInstalled());
        new PackageBuilder();
        self::assertSame([$this->name . '-static'], SharedLibraryUtil::pkgConfigFiles($this->name));
        self::assertFalse($package->isInstalled());
    }

    public function testAutoconfDefaultKeepsStaticOutputInBothModes(): void
    {
        $executor = (new \ReflectionClass(UnixAutoconfExecutor::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($executor, 'package'))->setValue($executor, new LibraryPackage($this->name, 'library'));
        $method = new \ReflectionMethod($executor, 'getDefaultConfigureArgs');
        $static = $method->invoke($executor);
        self::assertContains('--disable-shared', $static);
        self::assertContains('--enable-static', $static);
        new PackageBuilder(['with-shared-libs' => true]);
        $shared = $method->invoke($executor);
        self::assertContains('--enable-shared', $shared);
        self::assertContains('--enable-static', $shared);
        self::assertNotContains('--disable-shared', $shared);
    }

    public function testAbsoluteAndShortLibrarySelectionUseTheChosenMode(): void
    {
        $this->file('.a');
        $this->file('.so');
        $short = new \ReflectionMethod(SPCConfigUtil::class, 'getLibsString');
        $config = new SPCConfigUtil(['no_php' => true, 'libs_only_deps' => true]);
        self::assertSame('-l' . $this->name, $short->invoke($config, [$this->name], true));
        self::assertSame(BUILD_LIB_PATH . '/lib' . $this->name . '.a', $short->invoke($config, [$this->name], false));
        new PackageBuilder(['with-shared-libs' => true]);
        self::assertSame(BUILD_LIB_PATH . '/lib' . $this->name . '.so', $short->invoke($config, [$this->name], false));
        new PackageBuilder();
        self::assertSame(BUILD_LIB_PATH . '/lib' . $this->name . '.a', $short->invoke($config, [$this->name], false));
    }

    public function testSharedFlagsDoNotAffectTheNextStaticBuilder(): void
    {
        $original = getenv('SPC_DEFAULT_LDFLAGS');
        $originalPath = getenv('LD_LIBRARY_PATH');
        try {
            putenv('SPC_DEFAULT_LDFLAGS=-static -static-libgcc -static-libstdc++ -Wl,--as-needed -fno-static-chain');
            new PackageBuilder(['with-shared-libs' => true]);
            SharedLibraryUtil::configureEnvironment();
            self::assertSame('-Wl,--as-needed -fno-static-chain', getenv('SPC_DEFAULT_LDFLAGS'));
            self::assertStringStartsWith(BUILD_LIB_PATH, getenv('LD_LIBRARY_PATH'));
            new PackageBuilder();
            self::assertSame('-static -static-libgcc -static-libstdc++ -Wl,--as-needed -fno-static-chain', getenv('SPC_DEFAULT_LDFLAGS'));
            self::assertSame($originalPath, getenv('LD_LIBRARY_PATH'));
            SharedLibraryUtil::configureEnvironment();
            self::assertSame('-static -static-libgcc -static-libstdc++ -Wl,--as-needed -fno-static-chain', getenv('SPC_DEFAULT_LDFLAGS'));
        } finally {
            putenv('SPC_DEFAULT_LDFLAGS' . ($original === false ? '' : '=' . $original));
        }
    }

    public function testMuslGccBecomesDynamicOnlyInSharedMode(): void
    {
        $original = getenv('SPC_MUSL_DYNAMIC');
        putenv('SPC_MUSL_DYNAMIC=');
        try {
            new PackageBuilder(['with-shared-libs' => true]);
            self::assertFalse(new GccNativeToolchain()->isStatic());
        } finally {
            putenv('SPC_MUSL_DYNAMIC' . ($original === false ? '' : '=' . $original));
        }
    }

    public function testRuntimeDirectoryRejectsTraversal(): void
    {
        new PackageBuilder(['with-shared-libs' => true, 'with-shared-libdir' => '/usr/lib/../tmp']);
        $this->expectException(WrongUsageException::class);
        SharedLibraryUtil::runtimeDirectory();
    }

    public function testStrippingStaticFlagsPreservesUnrelatedFlags(): void
    {
        self::assertSame('-O2 -Wl,--as-needed -fno-static-chain', SharedLibraryUtil::withoutStaticFlags(
            '-O2 -static-pie -static-libgcc --static -Wl,-Bstatic -Wl,--as-needed -fno-static-chain'
        ));
    }

    public function testCmakeFlagsAndPkgConfigSwitchTogether(): void
    {
        $executor = (new \ReflectionClass(UnixCMakeExecutor::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($executor, 'package'))->setValue($executor, new LibraryPackage($this->name, 'library'));
        $method = new \ReflectionMethod($executor, 'getDefaultCMakeArgs');
        self::assertContains('-DBUILD_SHARED_LIBS=OFF', $method->invoke($executor));
        self::assertStringContainsString('set(PKG_CONFIG_ARGN "--static"', file_get_contents(SOURCE_PATH . '/toolchain.cmake'));
        new PackageBuilder(['with-shared-libs' => true]);
        self::assertContains('-DBUILD_SHARED_LIBS=ON', $method->invoke($executor));
        self::assertStringContainsString('set(PKG_CONFIG_ARGN ""', file_get_contents(SOURCE_PATH . '/toolchain.cmake'));
        new PackageBuilder();
        self::assertContains('-DBUILD_SHARED_LIBS=OFF', $method->invoke($executor));
        self::assertStringContainsString('set(PKG_CONFIG_ARGN "--static"', file_get_contents(SOURCE_PATH . '/toolchain.cmake'));
    }

    public function testSharedMetadataDoesNotReplaceStaticMetadata(): void
    {
        $configs = ['fixture' => ['type' => 'library', 'artifact' => 'fixture', 'static-libs' => ['libfixture.a'],
            'shared-libs@linux' => ['libfixture.so'], 'static-only' => false,
            'pkg-configs' => ['fixture-static'], 'shared-pkg-configs' => ['fixture']]];
        \StaticPHP\Config\ConfigValidator::validateAndLintPackages('fixture.yml', $configs);
        self::assertSame(['libfixture.a'], $configs['fixture']['static-libs']);
        self::assertFalse($configs['fixture']['static-only']);
        self::assertSame(['fixture-static'], $configs['fixture']['pkg-configs']);
        self::assertSame(['fixture'], $configs['fixture']['shared-pkg-configs']);
    }

    public function testGitSnapshotVersionsUseTheCommitTimeAndPortableNumericComponents(): void
    {
        $directory = sys_get_temp_dir() . '/spc-git-version-' . bin2hex(random_bytes(6));
        mkdir($directory);
        try {
            (new \Symfony\Component\Process\Process(['git', 'init', '--quiet', $directory]))->mustRun();
            $environment = ['GIT_AUTHOR_DATE' => '1577836800 +0000', 'GIT_COMMITTER_DATE' => '1577836800 +0000'];
            (new \Symfony\Component\Process\Process(['git', '-C', $directory, '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.invalid',
                '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', 'commit', '--allow-empty', '--quiet', '-m', 'fixture'], null, $environment))->mustRun();
            $package = $this->createMock(LibraryPackage::class);
            $package->method('getSourceDir')->willReturn($directory);
            $manifest = (new \ReflectionClass(\StaticPHP\Util\SharedLibraryManifest::class))->newInstanceWithoutConstructor();
            $method = new \ReflectionMethod($manifest, 'sourceVersion');
            foreach (['dev-main+abcdef', 'dev-v1.75.x+9b63ce0', 'dev-1.75+abcdef'] as $snapshot) {
                $version = $method->invoke($manifest, $package, ['cache_type' => 'git', 'dirname' => $directory,
                    'version' => $snapshot, 'hash' => 'abcdef']);
                self::assertSame('0.0.1577836800', $version);
                self::assertMatchesRegularExpression('/^[0-9]+\.[0-9]+\.[0-9]+$/D', $version);
            }
        } finally {
            FileSystem::removeDir($directory);
        }
    }

    public function testFixedArchiveVersionsComeFromAnUnambiguousFilename(): void
    {
        $manifest = (new \ReflectionClass(\StaticPHP\Util\SharedLibraryManifest::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($manifest, 'sourceVersion');
        $package = $this->createMock(LibraryPackage::class);
        foreach (['bzip2-1.0.8.tar.gz' => '1.0.8', 'some-lib-2.0.0-rc3.tar.xz' => '2.0.0-rc3'] as $filename => $version) {
            self::assertSame($version, $method->invoke(
                $manifest,
                $package,
                ['cache_type' => 'archive', 'filename' => $filename, 'version' => null]
            ));
        }
    }

    public function testReleaseTagVersionsUsePortableUpstreamNumbers(): void
    {
        $manifest = (new \ReflectionClass(\StaticPHP\Util\SharedLibraryManifest::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($manifest, 'sourceVersion');
        $package = $this->createMock(LibraryPackage::class);
        foreach (['curl-8_23_0' => '8.23.0', 'VER-2-15-0' => '2.15.0', 'release-77-1' => '77.1',
            '7.1.2-35' => '7.1.2.35', 'libedit-20260512-3.1' => '20260512.3.1',
            'release-2.1.13-stable' => '2.1.13', '1.0.22-RELEASE' => '1.0.22',
            'libssh2-1.11.1' => '1.11.1', 'REL_18_6' => '18.6'] as $tag => $version) {
            self::assertSame($version, $method->invoke($manifest, $package, ['cache_type' => 'archive', 'version' => $tag]));
        }
    }

    public function testMirrorVersionsComeFromLiteralUpstreamSourceMetadata(): void
    {
        $directory = sys_get_temp_dir() . '/spc-source-version-' . bin2hex(random_bytes(6));
        mkdir($directory);
        try {
            $package = $this->createMock(LibraryPackage::class);
            $package->method('getSourceDir')->willReturn($directory);
            $manifest = (new \ReflectionClass(\StaticPHP\Util\SharedLibraryManifest::class))->newInstanceWithoutConstructor();
            $method = new \ReflectionMethod($manifest, 'sourceVersion');
            foreach (['.tarball-version' => ["1.0\n", '1.0'], 'version.sh' => ["VERSION_NUMBER=1.5\nRELEASE_DATE=2026-10-17\n", '1.5'],
                'configure' => ["PACKAGE_VERSION='3.45.2'\n", '3.45.2'], 'VERSION' => ["5:0:10\t6.6\t20251230\n", '6.6']] as $file => [$contents, $version]) {
                file_put_contents($directory . '/' . $file, $contents);
                self::assertSame($version, $method->invoke($manifest, $package, ['cache_type' => 'archive', 'version' => null]));
                unlink($directory . '/' . $file);
            }
            self::assertSame('2.20', $method->invoke($manifest, $package, ['cache_type' => 'archive', 'version' => 'lcms2.20']));
        } finally {
            FileSystem::removeDir($directory);
        }
    }

    public function testSharedSnapshotOmitsTheCombinedInfoIndex(): void
    {
        $directory = BUILD_ROOT_PATH . '/share/info';
        FileSystem::createDir($directory);
        $index = $directory . '/dir';
        self::assertFileDoesNotExist($index);
        foreach ([$index, $directory . '/' . $this->name . '.info'] as $file) {
            file_put_contents($file, 'fixture documentation');
            $this->files[] = $file;
        }
        $manifest = (new \ReflectionClass(\StaticPHP\Util\SharedLibraryManifest::class))->newInstanceWithoutConstructor();
        $snapshot = (new \ReflectionMethod($manifest, 'snapshot'))->invoke($manifest);
        self::assertArrayNotHasKey('share/info/dir', $snapshot);
        self::assertArrayHasKey('share/info/' . $this->name . '.info', $snapshot);
    }

    public function testUnversionedArchiveFailsRatherThanInventingAVersion(): void
    {
        $manifest = (new \ReflectionClass(\StaticPHP\Util\SharedLibraryManifest::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($manifest, 'sourceVersion');
        $this->expectException(WrongUsageException::class);
        $method->invoke(
            $manifest,
            $this->createMock(LibraryPackage::class),
            ['cache_type' => 'archive', 'filename' => 'release-latest.tar.gz', 'version' => null]
        );
    }

    private function file(string $suffix): void
    {
        FileSystem::createDir(BUILD_LIB_PATH);
        $path = BUILD_LIB_PATH . '/lib' . $this->name . $suffix;
        file_put_contents($path, 'fixture');
        $this->files[] = $path;
    }
}
