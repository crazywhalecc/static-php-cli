<?php

declare(strict_types=1);

namespace SPC\store\pkg;

use SPC\builder\linux\SystemUtil;
use SPC\store\Downloader;
use SPC\store\FileSystem;
use SPC\store\LockFile;

class GoXcaddy extends CustomPackage
{
    public static function isInstalled(): bool
    {
        $arch = arch2gnu(php_uname('m'));
        $os = match (PHP_OS_FAMILY) {
            'Windows' => 'win',
            'Darwin' => 'macos',
            'BSD' => 'freebsd',
            default => 'linux',
        };

        $packageName = "go-xcaddy-{$arch}-{$os}";
        $pkgroot = PKG_ROOT_PATH;
        $folder = "{$pkgroot}/{$packageName}";

        return is_dir($folder) && is_file("{$folder}/bin/go") && is_file("{$folder}/bin/xcaddy");
    }

    public function getSupportName(): array
    {
        return [
            'go-xcaddy-x86_64-linux',
            'go-xcaddy-x86_64-macos',
            'go-xcaddy-aarch64-linux',
            'go-xcaddy-aarch64-macos',
        ];
    }

    public function fetch(string $name, bool $force = false, ?array $config = null): void
    {
        $pkgroot = PKG_ROOT_PATH;
        $go_exec = "{$pkgroot}/{$name}/bin/go";
        $xcaddy_exec = "{$pkgroot}/{$name}/bin/xcaddy";
        if ($force) {
            FileSystem::removeDir("{$pkgroot}/{$name}");
        }
        if (file_exists($go_exec) && file_exists($xcaddy_exec)) {
            return;
        }
        $arch = match (explode('-', $name)[2]) {
            'x86_64' => 'amd64',
            'aarch64' => 'arm64',
            default => throw new \InvalidArgumentException('Unsupported architecture: ' . $name),
        };
        $os = match (explode('-', $name)[3]) {
            'linux' => 'linux',
            'macos' => 'darwin',
            default => throw new \InvalidArgumentException('Unsupported OS: ' . $name),
        };
        $go_version = '1.24.4';
        $config = [
            'type' => 'url',
            'url' => "https://go.dev/dl/go{$go_version}.{$os}-{$arch}.tar.gz",
        ];
        Downloader::downloadPackage($name, $config, $force);
    }

    public function extract(string $name): void
    {
        $pkgroot = PKG_ROOT_PATH;
        $go_exec = "{$pkgroot}/{$name}/bin/go";
        $xcaddy_exec = "{$pkgroot}/{$name}/bin/xcaddy";
        if (file_exists($go_exec) && file_exists($xcaddy_exec)) {
            return;
        }
        $lock = json_decode(FileSystem::readFile(LockFile::LOCK_FILE), true);
        $source_type = $lock[$name]['source_type'];
        $filename = DOWNLOAD_PATH . '/' . ($lock[$name]['filename'] ?? $lock[$name]['dirname']);
        $extract = $lock[$name]['move_path'] ?? "{$pkgroot}/{$name}";

        FileSystem::extractPackage($name, $source_type, $filename, $extract);

        $sanitizedPath = getenv('PATH');
        if (PHP_OS_FAMILY === 'Linux' && !SystemUtil::isMuslDist()) {
            $sanitizedPath = preg_replace('#(:?/?[^:]*musl[^:]*)#', '', $sanitizedPath);
            $sanitizedPath = preg_replace('#^:|:$|::#', ':', $sanitizedPath); // clean up colons
        }

        // install xcaddy without using musl tools, xcaddy build requires dynamic linking
        shell()
            ->appendEnv([
                'PATH' => "{$pkgroot}/{$name}/bin:" . $sanitizedPath,
                'GOROOT' => "{$pkgroot}/{$name}",
                'GOBIN' => "{$pkgroot}/{$name}/bin",
                'GOPATH' => "{$pkgroot}/go",
            ])
            ->exec('CC=cc go install github.com/caddyserver/xcaddy/cmd/xcaddy@latest');
    }

    public static function getEnvironment(): array
    {
        $arch = arch2gnu(php_uname('m'));
        $os = match (PHP_OS_FAMILY) {
            'Windows' => 'win',
            'Darwin' => 'macos',
            'BSD' => 'freebsd',
            default => 'linux',
        };

        $packageName = "go-xcaddy-{$arch}-{$os}";
        $pkgroot = PKG_ROOT_PATH;

        return [
            'PATH' => "{$pkgroot}/{$packageName}/bin",
            'GOROOT' => "{$pkgroot}/{$packageName}",
            'GOBIN' => "{$pkgroot}/{$packageName}/bin",
            'GOPATH' => "{$pkgroot}/go",
        ];
    }
}
