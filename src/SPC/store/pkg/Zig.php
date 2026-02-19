<?php

declare(strict_types=1);

namespace SPC\store\pkg;

use SPC\exception\DownloaderException;
use SPC\exception\WrongUsageException;
use SPC\store\CurlHook;
use SPC\store\Downloader;
use SPC\store\FileSystem;
use SPC\store\LockFile;

class Zig extends CustomPackage
{
    public static function isInstalled(): bool
    {
        $path = self::getPath();
        $files = ['zig', 'zig-cc', 'zig-c++', 'zig-ar', 'zig-ld.lld', 'zig-ranlib', 'zig-objcopy'];
        foreach ($files as $file) {
            if (!file_exists("{$path}/{$file}")) {
                return false;
            }
        }
        return true;
    }

    public function getSupportName(): array
    {
        return [
            'zig-x86_64-linux',
            'zig-aarch64-linux',
            'zig-x86_64-macos',
            'zig-aarch64-macos',
            'zig-x86_64-win',
        ];
    }

    public function fetch(string $name, bool $force = false, ?array $config = null): void
    {
        $pkgroot = PKG_ROOT_PATH;
        $zig_exec = match (PHP_OS_FAMILY) {
            'Windows' => "{$pkgroot}/{$name}/zig.exe",
            default => "{$pkgroot}/{$name}/zig",
        };

        if ($force) {
            FileSystem::removeDir("{$pkgroot}/{$name}");
        }

        if (file_exists($zig_exec)) {
            return;
        }

        $parts = explode('-', $name);
        $arch = $parts[1];
        $os = $parts[2];

        $zig_arch = match ($arch) {
            'x86_64', 'aarch64' => $arch,
            default => throw new WrongUsageException('Unsupported architecture: ' . $arch),
        };

        $zig_os = match ($os) {
            'linux' => 'linux',
            'macos' => 'macos',
            'win' => 'windows',
            default => throw new WrongUsageException('Unsupported OS: ' . $os),
        };

        $index_json = json_decode(Downloader::curlExec('https://ziglang.org/download/index.json', hooks: [[CurlHook::class, 'setupGithubToken']]), true);

        $latest_version = null;
        foreach ($index_json as $version => $data) {
            // Skip the master branch, get the latest stable release
            if ($version !== 'master') {
                $latest_version = $version;
                break;
            }
        }

        if (!$latest_version) {
            throw new DownloaderException('Could not determine latest Zig version');
        }

        logger()->info("Installing Zig version {$latest_version}");

        $platform_key = "{$zig_arch}-{$zig_os}";
        if (!isset($index_json[$latest_version][$platform_key])) {
            throw new DownloaderException("No download available for {$platform_key} in Zig version {$latest_version}");
        }

        $download_info = $index_json[$latest_version][$platform_key];
        $url = $download_info['tarball'];
        $filename = basename($url);

        $pkg = [
            'type' => 'url',
            'url' => $url,
            'filename' => $filename,
        ];

        Downloader::downloadPackage($name, $pkg, $force);
    }

    public function extract(string $name): void
    {
        $pkgroot = PKG_ROOT_PATH;
        $zig_bin_dir = "{$pkgroot}/zig";

        $files = ['zig', 'zig-cc', 'zig-c++', 'zig-ar', 'zig-ld.lld', 'zig-ranlib', 'zig-objcopy'];
        $all_exist = true;
        foreach ($files as $file) {
            if (!file_exists("{$zig_bin_dir}/{$file}")) {
                $all_exist = false;
                break;
            }
        }
        if ($all_exist) {
            return;
        }

        $lock = json_decode(FileSystem::readFile(LockFile::LOCK_FILE), true);
        $source_type = $lock[$name]['source_type'];
        $filename = DOWNLOAD_PATH . '/' . ($lock[$name]['filename'] ?? $lock[$name]['dirname']);
        $extract = "{$pkgroot}/zig";

        FileSystem::extractPackage($name, $source_type, $filename, $extract);

        $this->createZigCcScript($zig_bin_dir);
    }

    public static function getEnvironment(): array
    {
        return [];
    }

    public static function getPath(): ?string
    {
        return PKG_ROOT_PATH . '/zig';
    }

    private function createZigCcScript(string $bin_dir): void
    {
        $script_path = __DIR__ . '/../scripts/zig-cc.sh';
        $script_content = file_get_contents($script_path);

        file_put_contents("{$bin_dir}/zig-cc", $script_content);
        chmod("{$bin_dir}/zig-cc", 0755);

        $script_content = str_replace('zig cc', 'zig c++', $script_content);
        file_put_contents("{$bin_dir}/zig-c++", $script_content);
        file_put_contents("{$bin_dir}/zig-ar", "#!/usr/bin/env bash\nexec zig ar $@");
        file_put_contents("{$bin_dir}/zig-ld.lld", "#!/usr/bin/env bash\nexec zig ld.lld $@");
        file_put_contents("{$bin_dir}/zig-ranlib", "#!/usr/bin/env bash\nexec zig ranlib $@");
        file_put_contents("{$bin_dir}/zig-objcopy", "#!/usr/bin/env bash\nexec zig objcopy $@");
        chmod("{$bin_dir}/zig-c++", 0755);
        chmod("{$bin_dir}/zig-ar", 0755);
        chmod("{$bin_dir}/zig-ld.lld", 0755);
        chmod("{$bin_dir}/zig-ranlib", 0755);
        chmod("{$bin_dir}/zig-objcopy", 0755);
    }
}
