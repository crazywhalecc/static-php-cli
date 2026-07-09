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

/**
 * Full CPython for Windows from the official python.org nuget package. It is a plain zip
 * (python.exe plus stdlib under tools/, venv and ensurepip included), so it installs into
 * the pkgroot without touching the system. Used by the meson tool package.
 */
class python_win
{
    #[CustomBinary('python-win', [
        'windows-x86_64',
    ])]
    public function downBinary(ArtifactDownloader $downloader): DownloadResult
    {
        $version = $this->getLatestVersion($downloader->getRetry());

        $url = "https://api.nuget.org/v3-flatcontainer/python/{$version}/python.{$version}.nupkg";
        // .nupkg is a zip; name the cache file .zip so the extractor treats it as one
        $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . "python-win-{$version}.zip";
        default_shell()->executeCurlDownload($url, $path, retries: $downloader->getRetry());

        return DownloadResult::archive(basename($path), ['url' => $url, 'version' => $version], extract: '{pkg_root_path}/python-win', version: $version);
    }

    #[CustomBinaryCheckUpdate('python-win', ['windows-x86_64'])]
    public function checkUpdateBinary(?string $old_version): CheckUpdateResult
    {
        $version = $this->getLatestVersion();
        return new CheckUpdateResult(
            old: $old_version,
            new: $version,
            needUpdate: $old_version === null || $version !== $old_version,
        );
    }

    #[AfterBinaryExtract('python-win', ['windows-x86_64'])]
    public function afterExtract(string $target_path): void
    {
        if (!file_exists("{$target_path}\\tools\\python.exe")) {
            throw new DownloaderException("Python installation appears incomplete: python.exe not found at {$target_path}\\tools\\python.exe");
        }
    }

    private function getLatestVersion(int $retries = 0): string
    {
        $index = default_shell()->executeCurl('https://api.nuget.org/v3-flatcontainer/python/index.json', retries: $retries);
        $versions = $index ? (json_decode($index, true)['versions'] ?? []) : [];
        $stable = array_filter($versions, fn ($v) => preg_match('/^\d+\.\d+\.\d+$/', $v));
        if ($stable === []) {
            throw new DownloaderException('Failed to get Python versions from the nuget index');
        }
        return end($stable);
    }
}
