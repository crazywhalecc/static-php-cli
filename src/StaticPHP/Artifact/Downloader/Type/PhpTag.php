<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Exception\DownloaderException;

/** php-src git tag archive, used for versions php.net does not publish (yet) */
class PhpTag implements DownloadTypeInterface, CheckUpdateInterface
{
    use GitHubTokenSetupTrait;

    public const string TAGS_API = 'https://api.github.com/repos/php/php-src/git/matching-refs/tags/{prefix}';

    public const string ARCHIVE_URL = 'https://github.com/php/php-src/archive/refs/tags/php-{version}.tar.gz';

    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult
    {
        $version = $this->resolveLatestTag($config['version'], $downloader->getRetry());
        $url = str_replace('{version}', $version, self::ARCHIVE_URL);
        logger()->notice("PHP {$version} is a pre-release, downloading its source archive from {$url}");
        $filename = "php-{$version}.tar.gz";
        default_shell()->executeCurlDownload($url, DOWNLOAD_PATH . "/{$filename}", retries: $downloader->getRetry());
        return DownloadResult::archive($filename, config: $config, extract: $config['extract'] ?? null, version: $version, downloader: static::class);
    }

    public function checkUpdate(string $name, array $config, ?string $old_version, ArtifactDownloader $downloader): CheckUpdateResult
    {
        $new_version = $this->resolveLatestTag($config['version'], $downloader->getRetry());
        return new CheckUpdateResult(
            old: $old_version,
            new: $new_version,
            needUpdate: $old_version === null || $new_version !== $old_version,
        );
    }

    /**
     * Resolve the highest php-src git tag matching the requested version.
     * Accepts a branch ('8.6', picks the latest 8.6.x tag) or an exact version ('8.6.0RC1').
     */
    protected function resolveLatestTag(string $phpver, int $retries = 0): string
    {
        $is_branch = preg_match('/^\d+\.\d+$/', $phpver) === 1;
        $prefix = $is_branch ? "php-{$phpver}." : "php-{$phpver}";
        $url = str_replace('{prefix}', $prefix, self::TAGS_API);
        logger()->debug("PHP version {$phpver} is not published on php.net, looking it up in php-src tags from {$url}");

        $data = default_shell()->executeCurl($url, headers: $this->getGitHubTokenHeaders(), retries: $retries);
        if ($data === false) {
            throw new DownloaderException("Failed to fetch php-src git tags for PHP version {$phpver}");
        }
        $data = json_decode($data, true);
        if (!is_array($data)) {
            throw new DownloaderException("Invalid php-src git tag list received for PHP version {$phpver}");
        }

        $pattern = '/^php-(' . preg_quote($phpver, '/') . ($is_branch ? '\.\d+' : '') . '(?:(?:alpha|beta|RC)\d+)?)$/';
        $versions = [];
        foreach ($data as $ref) {
            $tag = substr((string) ($ref['ref'] ?? ''), strlen('refs/tags/'));
            if (preg_match($pattern, $tag, $match) === 1) {
                $versions[] = $match[1];
            }
        }
        if (empty($versions)) {
            throw new DownloaderException("PHP version {$phpver} is not available on php.net nor tagged in php-src.");
        }
        usort($versions, version_compare(...));
        return end($versions);
    }
}
