<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Exception\DownloaderException;
use StaticPHP\Runtime\SystemTarget;

class HostedPackageBin implements DownloadTypeInterface
{
    use GitHubTokenSetupTrait;

    public const string BASE_REPO = 'static-php/package-bin';

    public const array ASSET_MATCHES = [
        'linux' => '{name}-{arch}-{os}-{libc}-{libcver}.txz',
        'darwin' => '{name}-{arch}-{os}.txz',
        'windows' => '{name}-{arch}-{os}.tgz',
    ];

    private static array $release_info = [];

    public static function getReleaseInfo(): array
    {
        if (empty(self::$release_info)) {
            $rel = (new GitHubRelease())->getGitHubReleases('hosted', self::BASE_REPO);
            if (empty($rel)) {
                throw new DownloaderException('No releases found for hosted package-bin');
            }
            self::$release_info = $rel[0];
        }
        return self::$release_info;
    }

    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult
    {
        $info = self::getReleaseInfo();
        $replace = [
            '{name}' => $name,
            '{arch}' => SystemTarget::getTargetArch(),
            '{os}' => strtolower(SystemTarget::getTargetOS()),
            '{libc}' => SystemTarget::getLibc() ?? 'default',
            '{libcver}' => SystemTarget::getLibcVersion() ?? 'default',
        ];
        $find_str = str_replace(array_keys($replace), array_values($replace), self::ASSET_MATCHES[strtolower(SystemTarget::getTargetOS())]);
        foreach ($info['assets'] as $asset) {
            if ($asset['name'] === $find_str) {
                $download_url = $asset['browser_download_url'];
                $filename = $asset['name'];
                $version = ltrim($info['tag_name'], 'v');
                logger()->debug("Downloading hosted package-bin {$name} version {$version} from GitHub: {$download_url}");
                $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
                $headers = $this->getGitHubTokenHeaders();
                default_shell()->executeCurlDownload($download_url, $path, headers: $headers, retries: $downloader->getRetry());
                return DownloadResult::archive($filename, $config, extract: $config['extract'] ?? null, version: $version);
            }
        }
        throw new DownloaderException("No matching asset found for hosted package-bin {$name}: {$find_str}");
    }
}
