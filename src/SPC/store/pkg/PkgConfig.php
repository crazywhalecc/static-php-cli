<?php

declare(strict_types=1);

namespace SPC\store\pkg;

use SPC\store\Downloader;
use SPC\store\FileSystem;
use SPC\store\LockFile;
use SPC\util\SPCTarget;

class PkgConfig extends CustomPackage
{
    public static function isInstalled(): bool
    {
        $arch = arch2gnu(php_uname('m'));
        $os = match (PHP_OS_FAMILY) {
            'Darwin' => 'macos',
            default => 'linux',
        };
        $name = "pkg-config-{$arch}-{$os}";
        return is_file(PKG_ROOT_PATH . "/{$name}/bin/pkg-config");
    }

    public function getSupportName(): array
    {
        return [
            'pkg-config-x86_64-linux',
            'pkg-config-aarch64-linux',
            'pkg-config-x86_64-macos',
            'pkg-config-aarch64-macos',
        ];
    }

    public function fetch(string $name, bool $force = false, ?array $config = null): void
    {
        $pkgroot = PKG_ROOT_PATH;
        $bin = "{$pkgroot}/{$name}/bin/pkg-config";
        if ($force) {
            FileSystem::removeDir("{$pkgroot}/{$name}");
        }
        if (file_exists($bin)) {
            return;
        }
        // Use known stable pkg-config source tarball (same as config/source.json)
        $pkg = [
            'type' => 'url',
            'url' => 'https://dl.static-php.dev/static-php-cli/deps/pkg-config/pkg-config-0.29.2.tar.gz',
            'filename' => 'pkg-config-0.29.2.tar.gz',
        ];
        Downloader::downloadPackage($name, $pkg, $force);
    }

    public function extract(string $name): void
    {
        $pkgroot = PKG_ROOT_PATH;
        $prefix = "{$pkgroot}/{$name}";
        $bin = "{$prefix}/bin/pkg-config";
        if (file_exists($bin)) {
            return;
        }
        $lock = json_decode(FileSystem::readFile(LockFile::LOCK_FILE), true);
        $source_type = $lock[$name]['source_type'];
        $filename = DOWNLOAD_PATH . '/' . ($lock[$name]['filename'] ?? $lock[$name]['dirname']);
        $srcdir = "{$pkgroot}/{$name}/src";
        FileSystem::extractPackage($name, $source_type, $filename, $srcdir);

        // build from source into package prefix
        $env = [
            'CFLAGS' => getenv('SPC_DEFAULT_C_FLAGS') ?: '-Os -Wno-int-conversion',
            'LDFLAGS' => (SPCTarget::isStatic() ? '--static' : ''),
            'PKG_CONFIG' => 'pkg-config',
            'PKG_CONFIG_PATH' => BUILD_ROOT_PATH . '/lib/pkgconfig',
        ];
        $shell = shell()
            ->setEnv([
                'CC' => 'cc',
                'CXX' => 'c++',
                'AR' => 'ar',
                'LD' => 'ld',
            ])
            ->appendEnv($env)->cd($srcdir);
        $shell->exec(
            "./configure --prefix='{$prefix}' " .
            '--with-internal-glib '.
            '--disable-host-tool '.
            '--without-sysroot '.
            '--without-system-include-path '.
            '--without-system-library-path '.
            '--without-pc-path',
        );
        $shell->exec('make -j' . (getenv('SPC_CONCURRENCY') ?: '1'));
        $shell->exec('make install-exec');
        if (is_file($bin)) {
            @shell()->exec('strip ' . $bin);
        }
    }

    public static function getEnvironment(): array
    {
        $arch = arch2gnu(php_uname('m'));
        $os = match (PHP_OS_FAMILY) {
            'Darwin' => 'macos',
            default => 'linux',
        };
        $name = "pkg-config-{$arch}-{$os}";
        return [
            'PATH' => PKG_ROOT_PATH . "/{$name}/bin",
        ];
    }
}
