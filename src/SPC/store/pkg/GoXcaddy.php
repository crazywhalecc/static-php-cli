<?php

declare(strict_types=1);

namespace SPC\store\pkg;

use SPC\store\Downloader;
use SPC\store\FileSystem;
use SPC\store\LockFile;

class GoXcaddy extends CustomPackage
{
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
        $extract = $lock[$name]['move_path'] === null ? "{$pkgroot}/{$name}" : $lock[$name]['move_path'];

        FileSystem::extractPackage($name, $source_type, $filename, $extract);

        // install xcaddy
        shell()
            ->appendEnv([
                'PATH' => "{$pkgroot}/{$name}/bin:" . getenv('PATH'),
                'GOROOT' => "{$pkgroot}/{$name}",
                'GOBIN' => "{$pkgroot}/{$name}/bin",
                'GOPATH' => "{$pkgroot}/go",
            ])
            ->exec("{$go_exec} install github.com/caddyserver/xcaddy/cmd/xcaddy@latest");
    }
}
