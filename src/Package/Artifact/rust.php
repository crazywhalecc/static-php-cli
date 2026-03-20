<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Artifact\Downloader\Type\CheckUpdateResult;
use StaticPHP\Attribute\Artifact\AfterBinaryExtract;
use StaticPHP\Attribute\Artifact\CustomBinary;
use StaticPHP\Attribute\Artifact\CustomBinaryCheckUpdate;
use StaticPHP\Exception\DownloaderException;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\System\LinuxUtil;

class rust
{
    #[CustomBinary('rust', [
        'linux-x86_64',
        'linux-aarch64',
    ])]
    public function downBinary(ArtifactDownloader $downloader): DownloadResult
    {
        // determine distro first
        $distro = LinuxUtil::isMuslDist() ? 'musl' : 'gnu';
        $arch = SystemTarget::getTargetArch();

        // get latest rust version from link
        $toml_config = default_shell()->executeCurl('https://static.rust-lang.org/dist/channel-rust-stable.toml', retries: $downloader->getRetry());
        // parse toml by regex since we want to avoid adding a toml parser dependency just for this
        $cnt = preg_match_all('/^version = "([^"]+)"$/m', $toml_config ?: '', $matches);
        if (!$cnt) {
            throw new DownloaderException('Failed to parse Rust version from channel config');
        }
        $versions = $matches[1];
        // strip version num \d.\d.\d (some version number is like "x.x.x (abcdefg 1970-01-01)"
        $versions = array_filter(array_map(fn ($v) => preg_match('/^(\d+\.\d+\.\d+)/', $v, $m) ? $m[1] : null, $versions));
        usort($versions, 'version_compare');
        $latest_version = end($versions);
        if (!$latest_version) {
            throw new DownloaderException('Could not determine latest Rust version');
        }

        // merge download link
        $download_url = "https://static.rust-lang.org/dist/rust-{$latest_version}-{$arch}-unknown-linux-{$distro}.tar.xz";
        $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . basename($download_url);
        default_shell()->executeCurlDownload($download_url, $path, retries: $downloader->getRetry());
        return DownloadResult::archive(basename($path), ['url' => $download_url, 'version' => $latest_version], extract: PKG_ROOT_PATH . '/rust-install', verified: false, version: $latest_version);
    }

    #[CustomBinaryCheckUpdate('rust', [
        'linux-x86_64',
        'linux-aarch64',
    ])]
    public function checkUpdateBinary(?string $old_version, ArtifactDownloader $downloader): CheckUpdateResult
    {
        $toml_config = default_shell()->executeCurl('https://static.rust-lang.org/dist/channel-rust-stable.toml', retries: $downloader->getRetry());
        $cnt = preg_match_all('/^version = "([^"]+)"$/m', $toml_config ?: '', $matches);
        if (!$cnt) {
            throw new DownloaderException('Failed to parse Rust version from channel config');
        }
        $versions = array_filter(array_map(fn ($v) => preg_match('/^(\d+\.\d+\.\d+)/', $v, $m) ? $m[1] : null, $matches[1]));
        usort($versions, 'version_compare');
        $latest_version = end($versions);
        if (!$latest_version) {
            throw new DownloaderException('Could not determine latest Rust version');
        }
        return new CheckUpdateResult(
            old: $old_version,
            new: $latest_version,
            needUpdate: $old_version === null || $latest_version !== $old_version,
        );
    }

    #[AfterBinaryExtract('rust', [
        'linux-x86_64',
        'linux-aarch64',
    ])]
    public function postExtractRust(string $target_path): void
    {
        $prefix = PKG_ROOT_PATH . '/rust';
        shell()->exec("cd {$target_path} && ./install.sh --prefix={$prefix}");
    }
}
