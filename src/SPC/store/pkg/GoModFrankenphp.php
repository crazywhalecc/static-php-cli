<?php

declare(strict_types=1);

namespace SPC\store\pkg;

use SPC\store\Downloader;
use SPC\store\FileSystem;

class GoModFrankenphp extends CustomPackage
{
    public function getSupportName(): array
    {
        return [
            'go-mod-frankenphp-x86_64-linux',
            'go-mod-frankenphp-x86_64-macos',
            'go-mod-frankenphp-aarch64-linux',
            'go-mod-frankenphp-aarch64-macos',
        ];
    }

    public function fetch(string $name, bool $force = false, ?array $config = null): void
    {
        $arch = match (explode('-', $name)[3]) {
            'x86_64' => 'amd64',
            'aarch64' => 'arm64',
            default => throw new \InvalidArgumentException('Unsupported architecture: ' . $name),
        };
        $os = match (explode('-', $name)[4]) {
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
        $lock = json_decode(FileSystem::readFile(DOWNLOAD_PATH . '/.lock.json'), true);
        $source_type = $lock[$name]['source_type'];
        $filename = DOWNLOAD_PATH . '/' . ($lock[$name]['filename'] ?? $lock[$name]['dirname']);
        $extract = $lock[$name]['move_path'] === null ? (PKG_ROOT_PATH . "{$pkgroot}/{$name}") : $lock[$name]['move_path'];

        FileSystem::extractPackage($name, $source_type, $filename, $extract);

        // install xcaddy
        $go_exec = PKG_ROOT_PATH . "{$pkgroot}/{$name}/bin/go";
        // $xcaddy_exec = PKG_ROOT_PATH . "$pkgroot/$name/bin/xcaddy";
        shell()->appendEnv([
            'PATH' => "{$pkgroot}/{$name}/bin:" . getenv('PATH'),
            'GOROOT' => "{$pkgroot}/{$name}",
            'GOBIN' => "{$pkgroot}/{$name}/bin",
            'GOPATH' => "{$pkgroot}/go",
        ])
            ->exec("{$go_exec} install github.com/caddyserver/xcaddy/cmd/xcaddy@latest");
        // TODO: Here to download dependencies for xcaddy and frankenphp first
    }
}
