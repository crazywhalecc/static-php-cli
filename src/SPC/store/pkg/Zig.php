<?php

declare(strict_types=1);

namespace SPC\store\pkg;

use SPC\store\CurlHook;
use SPC\store\Downloader;
use SPC\store\FileSystem;
use SPC\store\LockFile;

class Zig extends CustomPackage
{
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
            'Windows' => "{$pkgroot}/{$name}/bin/zig.exe",
            default => "{$pkgroot}/{$name}/bin/zig",
        };

        if (file_exists($zig_exec) && !$force) {
            return;
        }

        $parts = explode('-', $name);
        $arch = $parts[1];
        $os = $parts[2];

        $zig_arch = match ($arch) {
            'x86_64', 'aarch64' => $arch,
            default => throw new \InvalidArgumentException('Unsupported architecture: ' . $arch),
        };

        $zig_os = match ($os) {
            'linux' => 'linux',
            'macos' => 'macos',
            'win' => 'windows',
            default => throw new \InvalidArgumentException('Unsupported OS: ' . $os),
        };

        $index_json = json_decode(Downloader::curlExec('https://ziglang.org/download/index.json', hooks: [[CurlHook::class, 'setupGithubToken']]), true);

        $latest_version = null;
        foreach ($index_json as $version => $data) {
            $latest_version = $version;
            break;
        }

        if (!$latest_version) {
            throw new \RuntimeException('Could not determine latest Zig version');
        }

        logger()->info("Installing Zig version {$latest_version}");

        $platform_key = "{$zig_arch}-{$zig_os}";
        if (!isset($index_json[$latest_version][$platform_key])) {
            throw new \RuntimeException("No download available for {$platform_key} in Zig version {$latest_version}");
        }

        $download_info = $index_json[$latest_version][$platform_key];
        $url = $download_info['tarball'];
        $filename = basename($url);

        $config = [
            'type' => 'url',
            'url' => $url,
            'filename' => $filename,
        ];

        Downloader::downloadPackage($name, $config, $force);
    }

    public function extract(string $name): void
    {
        $pkgroot = PKG_ROOT_PATH;
        $zig_bin_dir = "{$pkgroot}/{$name}";
        $zig_exec = match (PHP_OS_FAMILY) {
            'Windows' => "{$zig_bin_dir}/zig.exe",
            default => "{$zig_bin_dir}/zig",
        };

        if (file_exists($zig_exec)) {
            if (!file_exists("{$zig_bin_dir}/zig-cc")) {
                $this->createZigCcScript($zig_bin_dir);
                return;
            }
            return;
        }

        $lock = json_decode(FileSystem::readFile(LockFile::LOCK_FILE), true);
        $source_type = $lock[$name]['source_type'];
        $filename = DOWNLOAD_PATH . '/' . ($lock[$name]['filename'] ?? $lock[$name]['dirname']);
        $extract = "{$pkgroot}/{$name}";

        FileSystem::extractPackage($name, $source_type, $filename, $extract);

        $this->createZigCcScript($zig_bin_dir);
    }

    public static function getEnvironment(): array
    {
        $arch = arch2gnu(php_uname('m'));
        $os = match (PHP_OS_FAMILY) {
            'Linux' => 'linux',
            'Windows' => 'win',
            'Darwin' => 'macos',
            'BSD' => 'freebsd',
            default => 'linux',
        };

        $packageName = "zig-{$arch}-{$os}";
        $path = PKG_ROOT_PATH . "/{$packageName}";

        return [
            'PATH' => $path,
        ];
    }

    private function createZigCcScript(string $bin_dir): void
    {
        $script_path = __DIR__ . '/../scripts/zig-cc.sh';
        $script_content = file_get_contents($script_path);

        file_put_contents("{$bin_dir}/zig-cc", $script_content);
        chmod("{$bin_dir}/zig-cc", 0755);

        $script_content = str_replace('zig cc', 'zig c++', $script_content);
        file_put_contents("{$bin_dir}/zig-c++", $script_content);
        chmod("{$bin_dir}/zig-c++", 0755);
    }
}
