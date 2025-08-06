<?php

declare(strict_types=1);

namespace SPC\Tests\util;

use PHPUnit\Framework\TestCase;
use SPC\exception\ExecutionException;
use SPC\util\PkgConfigUtil;

/**
 * @internal
 */
final class PkgConfigUtilTest extends TestCase
{
    private static string $originalPath;

    private static string $fakePkgConfigPath;

    public static function setUpBeforeClass(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Skip tests on Windows as pkg-config is not typically available
            self::markTestSkipped('PkgConfigUtil tests are not applicable on Windows.');
        }
        parent::setUpBeforeClass();

        // Save original PATH
        self::$originalPath = getenv('PATH');

        // Create fake pkg-config directory
        self::$fakePkgConfigPath = sys_get_temp_dir() . '/fake-pkg-config-' . uniqid();
        mkdir(self::$fakePkgConfigPath, 0755, true);

        // Create fake pkg-config executable
        self::createFakePkgConfig();

        // Add fake pkg-config to PATH
        putenv('PATH=' . self::$fakePkgConfigPath . ':' . self::$originalPath);
    }

    public static function tearDownAfterClass(): void
    {
        // Restore original PATH
        putenv('PATH=' . self::$originalPath);

        // Clean up fake pkg-config
        if (is_dir(self::$fakePkgConfigPath)) {
            self::removeDirectory(self::$fakePkgConfigPath);
        }

        parent::tearDownAfterClass();
    }

    /**
     * @dataProvider validPackageProvider
     */
    public function testGetCflagsWithValidPackage(string $package, string $expectedCflags): void
    {
        $result = PkgConfigUtil::getCflags($package);
        $this->assertEquals($expectedCflags, $result);
    }

    /**
     * @dataProvider validPackageProvider
     */
    public function testGetLibsArrayWithValidPackage(string $package, string $expectedCflags, array $expectedLibs): void
    {
        $result = PkgConfigUtil::getLibsArray($package);
        $this->assertEquals($expectedLibs, $result);
    }

    /**
     * @dataProvider invalidPackageProvider
     */
    public function testGetCflagsWithInvalidPackage(string $package): void
    {
        $this->expectException(ExecutionException::class);
        PkgConfigUtil::getCflags($package);
    }

    /**
     * @dataProvider invalidPackageProvider
     */
    public function testGetLibsArrayWithInvalidPackage(string $package): void
    {
        $this->expectException(ExecutionException::class);
        PkgConfigUtil::getLibsArray($package);
    }

    public static function invalidPackageProvider(): array
    {
        return [
            'invalid-package' => ['invalid-package'],
            'empty-string' => [''],
            'non-existent-package' => ['non-existent-package'],
        ];
    }

    public static function validPackageProvider(): array
    {
        return [
            'libxml2' => ['libxml-2.0', '-I/usr/include/libxml2', ['-lxml2', '']],
            'zlib' => ['zlib', '-I/usr/include', ['-lz', '']],
            'openssl' => ['openssl', '-I/usr/include/openssl', ['-lssl', '-lcrypto', '']],
        ];
    }

    /**
     * Create a fake pkg-config executable
     */
    private static function createFakePkgConfig(): void
    {
        $pkgConfigScript = self::$fakePkgConfigPath . '/pkg-config';

        $script = <<<'SCRIPT'
#!/bin/bash

# Fake pkg-config script for testing
# Shift arguments to get the package name
shift

case "$1" in
    --cflags-only-other)
        shift
        case "$1" in
            libxml-2.0)
                echo "-I/usr/include/libxml2"
                ;;
            zlib)
                echo "-I/usr/include"
                ;;
            openssl)
                echo "-I/usr/include/openssl"
                ;;
            *)
                echo "Package '$1' was not found in the pkg-config search path." >&2
                exit 1
                ;;
        esac
        ;;
    --libs-only-l)
        shift
        case "$1" in
            libxml-2.0)
                echo "-lxml2"
                ;;
            zlib)
                echo "-lz"
                ;;
            openssl)
                echo "-lssl -lcrypto"
                ;;
            *)
                echo "Package '$1' was not found in the pkg-config search path." >&2
                exit 1
                ;;
        esac
        ;;
    --libs-only-other)
        shift
        case "$1" in
            libxml-2.0)
                echo ""
                ;;
            zlib)
                echo ""
                ;;
            openssl)
                echo ""
                ;;
            *)
                echo "Package '$1' was not found in the pkg-config search path." >&2
                exit 1
                ;;
        esac
        ;;
    *)
        echo "Usage: pkg-config [OPTION] [PACKAGE]" >&2
        echo "Try 'pkg-config --help' for more information." >&2
        exit 1
        ;;
esac
SCRIPT;

        file_put_contents($pkgConfigScript, $script);
        chmod($pkgConfigScript, 0755);
    }

    /**
     * Remove directory recursively
     */
    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
