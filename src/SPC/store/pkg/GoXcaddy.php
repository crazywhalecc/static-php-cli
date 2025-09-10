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
        $folder = PKG_ROOT_PATH . '/go-xcaddy';
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
        $go_version = '1.25.0';
        $config = [
            'type' => 'url',
            'url' => "https://go.dev/dl/go{$go_version}.{$os}-{$arch}.tar.gz",
        ];
        Downloader::downloadPackage($name, $config, $force);
    }

    public function extract(string $name): void
    {
        $pkgroot = PKG_ROOT_PATH;
        $go_exec = "{$pkgroot}/go-xcaddy/bin/go";
        $xcaddy_exec = "{$pkgroot}/go-xcaddy/bin/xcaddy";
        if (file_exists($go_exec) && file_exists($xcaddy_exec)) {
            return;
        }
        $lock = json_decode(FileSystem::readFile(LockFile::LOCK_FILE), true);
        $source_type = $lock[$name]['source_type'];
        $filename = DOWNLOAD_PATH . '/' . ($lock[$name]['filename'] ?? $lock[$name]['dirname']);
        $extract = $lock[$name]['move_path'] ?? "{$pkgroot}/go-xcaddy";

        FileSystem::extractPackage($name, $source_type, $filename, $extract);

        $sanitizedPath = getenv('PATH');
        if (PHP_OS_FAMILY === 'Linux' && !SystemUtil::isMuslDist()) {
            $sanitizedPath = preg_replace('#(:?/?[^:]*musl[^:]*)#', '', $sanitizedPath);
            $sanitizedPath = preg_replace('#^:|:$|::#', ':', $sanitizedPath); // clean up colons
        }

        // install xcaddy without using musl tools, xcaddy build requires dynamic linking
        shell()
            ->appendEnv([
                'PATH' => "{$pkgroot}/go-xcaddy/bin:" . $sanitizedPath,
                'GOROOT' => "{$pkgroot}/go-xcaddy",
                'GOBIN' => "{$pkgroot}/go-xcaddy/bin",
                'GOPATH' => "{$pkgroot}/go",
            ])
            ->exec('CC=cc go install github.com/caddyserver/xcaddy/cmd/xcaddy@latest');
    }

    public static function getEnvironment(): array
    {
        $packageName = 'go-xcaddy';
        $pkgroot = PKG_ROOT_PATH;
        return [
            'GOROOT' => "{$pkgroot}/{$packageName}",
            'GOBIN' => "{$pkgroot}/{$packageName}/bin",
            'GOPATH' => "{$pkgroot}/go",
        ];
    }

    public static function getPath(): ?string
    {
        return PKG_ROOT_PATH . '/go-xcaddy/bin';
    }
}
