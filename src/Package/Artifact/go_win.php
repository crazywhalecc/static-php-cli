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
use StaticPHP\Util\GlobalEnvManager;

class go_win
{
    /** GOROOT for the Windows Go toolchain. */
    public static function path(): string
    {
        return PKG_ROOT_PATH . '/go-win';
    }

    /** Path to a binary inside go-win's bin/ (go.exe, gofmt.exe, …). */
    public static function binary(string $name = 'go.exe'): string
    {
        return self::path() . '/bin/' . $name;
    }

    public static function isInstalled(): bool
    {
        return is_file(self::binary());
    }

    #[CustomBinary('go-win', [
        'windows-x86_64',
    ])]
    public function downBinary(ArtifactDownloader $downloader): DownloadResult
    {
        // get version
        [$version] = explode("\n", default_shell()->executeCurl('https://go.dev/VERSION?m=text', retries: $downloader->getRetry()) ?: '');
        if ($version === '') {
            throw new DownloaderException('Failed to get latest Go version from https://go.dev/VERSION?m=text');
        }

        // find SHA256 hash from download page
        $page = default_shell()->executeCurl('https://go.dev/dl/', retries: $downloader->getRetry());
        if ($page === '' || $page === false) {
            throw new DownloaderException('Failed to get Go download page from https://go.dev/dl/');
        }

        $version_regex = str_replace('.', '\.', $version);
        $pattern = "/class=\"download\" href=\"\\/dl\\/{$version_regex}\\.windows-amd64\\.zip\">.*?<tt>([a-f0-9]{64})<\\/tt>/s";
        if (preg_match($pattern, $page, $matches)) {
            $hash = $matches[1];
        } else {
            throw new DownloaderException("Failed to find download hash for Go {$version} windows-amd64");
        }

        $url = "https://go.dev/dl/{$version}.windows-amd64.zip";
        $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . "{$version}.windows-amd64.zip";
        default_shell()->executeCurlDownload($url, $path, retries: $downloader->getRetry());

        // verify hash
        $file_hash = hash_file('sha256', $path);
        if ($file_hash !== $hash) {
            throw new DownloaderException("Hash mismatch for downloaded go-win binary. Expected {$hash}, got {$file_hash}");
        }

        return DownloadResult::archive(basename($path), ['url' => $url, 'version' => $version], extract: '{pkg_root_path}/go-win', verified: true, version: $version);
    }

    #[CustomBinaryCheckUpdate('go-win', ['windows-x86_64'])]
    public function checkUpdateBinary(?string $old_version): CheckUpdateResult
    {
        [$version] = explode("\n", default_shell()->executeCurl('https://go.dev/VERSION?m=text') ?: '');
        if ($version === '') {
            throw new DownloaderException('Failed to get latest Go version from https://go.dev/VERSION?m=text');
        }
        return new CheckUpdateResult(
            old: $old_version,
            new: $version,
            needUpdate: $old_version === null || $version !== $old_version,
        );
    }

    #[AfterBinaryExtract('go-win', ['windows-x86_64'])]
    public function afterExtract(string $target_path): void
    {
        if (!file_exists("{$target_path}\\bin\\go.exe")) {
            throw new DownloaderException("Go installation appears incomplete: go.exe not found at {$target_path}\\bin\\go.exe");
        }

        GlobalEnvManager::putenv("GOROOT={$target_path}");
        GlobalEnvManager::putenv("GOPATH={$target_path}\\gopath");
        GlobalEnvManager::putenv("GOCACHE={$target_path}\\gocache");
        GlobalEnvManager::putenv("GOMODCACHE={$target_path}\\gopath\\pkg\\mod");
        GlobalEnvManager::addPathIfNotExists("{$target_path}\\bin");
    }
}
