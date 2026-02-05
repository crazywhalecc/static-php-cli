<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Attribute\Artifact\AfterBinaryExtract;
use StaticPHP\Attribute\Artifact\CustomBinary;
use StaticPHP\Exception\DownloaderException;
use StaticPHP\Runtime\SystemTarget;

class zig
{
    #[CustomBinary('zig', [
        'linux-x86_64',
        'linux-aarch64',
        'macos-x86_64',
        'macos-aarch64',
    ])]
    public function downBinary(ArtifactDownloader $downloader): DownloadResult
    {
        $index_json = default_shell()->executeCurl('https://ziglang.org/download/index.json', retries: $downloader->getRetry());
        $index_json = json_decode($index_json ?: '', true);
        $latest_version = null;
        foreach ($index_json as $version => $data) {
            if ($version !== 'master') {
                $latest_version = $version;
                break;
            }
        }

        if (!$latest_version) {
            throw new DownloaderException('Could not determine latest Zig version');
        }
        $zig_arch = SystemTarget::getTargetArch();
        $zig_os = match (SystemTarget::getTargetOS()) {
            'Windows' => 'win',
            'Darwin' => 'macos',
            'Linux' => 'linux',
            default => throw new DownloaderException('Unsupported OS for Zig: ' . SystemTarget::getTargetOS()),
        };
        $platform_key = "{$zig_arch}-{$zig_os}";
        if (!isset($index_json[$latest_version][$platform_key])) {
            throw new DownloaderException("No download available for {$platform_key} in Zig version {$latest_version}");
        }
        $download_info = $index_json[$latest_version][$platform_key];
        $url = $download_info['tarball'];
        $sha256 = $download_info['shasum'];
        $filename = basename($url);
        $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
        default_shell()->executeCurlDownload($url, $path, retries: $downloader->getRetry());
        // verify hash
        $file_hash = hash_file('sha256', $path);
        if ($file_hash !== $sha256) {
            throw new DownloaderException("Hash mismatch for downloaded Zig binary. Expected {$sha256}, got {$file_hash}");
        }
        return DownloadResult::archive(basename($path), ['url' => $url, 'version' => $latest_version], extract: PKG_ROOT_PATH . '/zig', verified: true, version: $latest_version);
    }

    #[AfterBinaryExtract('zig', [
        'linux-x86_64',
        'linux-aarch64',
        'macos-x86_64',
        'macos-aarch64',
    ])]
    public function postExtractZig(string $target_path): void
    {
        $files = ['zig', 'zig-cc', 'zig-c++', 'zig-ar', 'zig-ld.lld', 'zig-ranlib', 'zig-objcopy'];
        $all_exist = true;
        foreach ($files as $file) {
            if (!file_exists("{$target_path}/{$file}")) {
                $all_exist = false;
                break;
            }
        }
        if ($all_exist) {
            return;
        }

        $script_path = ROOT_DIR . '/src/globals/scripts/zig-cc.sh';
        $script_content = file_get_contents($script_path);

        file_put_contents("{$target_path}/zig-cc", $script_content);
        chmod("{$target_path}/zig-cc", 0755);

        $script_content = str_replace('zig cc', 'zig c++', $script_content);
        file_put_contents("{$target_path}/zig-c++", $script_content);
        file_put_contents("{$target_path}/zig-ar", "#!/usr/bin/env bash\nexec zig ar $@");
        file_put_contents("{$target_path}/zig-ld.lld", "#!/usr/bin/env bash\nexec zig ld.lld $@");
        file_put_contents("{$target_path}/zig-ranlib", "#!/usr/bin/env bash\nexec zig ranlib $@");
        file_put_contents("{$target_path}/zig-objcopy", "#!/usr/bin/env bash\nexec zig objcopy $@");
        chmod("{$target_path}/zig-c++", 0755);
        chmod("{$target_path}/zig-ar", 0755);
        chmod("{$target_path}/zig-ld.lld", 0755);
        chmod("{$target_path}/zig-ranlib", 0755);
        chmod("{$target_path}/zig-objcopy", 0755);
    }
}
