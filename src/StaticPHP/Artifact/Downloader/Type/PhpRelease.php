<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Exception\DownloaderException;

class PhpRelease implements DownloadTypeInterface, ValidatorInterface, CheckUpdateInterface, CacheMatchInterface
{
    public const string DEFAULT_PHP_DOMAIN = 'https://www.php.net';

    public const string API_URL = '/releases/index.php?json&version={version}';

    public const string DOWNLOAD_URL = '/distributions/php-{version}.tar.xz';

    public const string GIT_URL = 'https://github.com/php/php-src.git';

    public const string GIT_REV = 'master';

    private ?string $sha256 = '';

    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult
    {
        $phpver = $downloader->getOption('with-php', '8.5');
        // Handle 'git' version to clone from php-src repository
        if ($phpver === 'git') {
            $this->sha256 = null;
            return (new Git())->download($name, ['url' => self::GIT_URL, 'rev' => self::GIT_REV], $downloader);
        }
        // php.net does not publish pre-releases: fall back to the php-src git tag archive
        if (($info = $this->fetchPhpReleaseInfo($phpver, $config, $downloader)) === null) {
            $this->sha256 = null;
            return (new PhpTag())->download($name, ['version' => $phpver, 'extract' => $config['extract'] ?? null], $downloader);
        }
        ['version' => $version, 'url' => $url, 'filename' => $filename, 'sha256' => $this->sha256] = $this->resolveRelease($info, $config);
        logger()->debug("Downloading PHP release {$version} from {$url}");
        $path = DOWNLOAD_PATH . "/{$filename}";
        default_shell()->executeCurlDownload($url, $path, retries: $downloader->getRetry());
        return DownloadResult::archive($filename, config: $config, extract: $config['extract'] ?? null, version: $version, downloader: static::class);
    }

    public function validate(string $name, array $config, ArtifactDownloader $downloader, DownloadResult $result): bool
    {
        if ($this->sha256 === null) {
            logger()->debug('Php-src is downloaded from non-release source, skipping validation.');
            return true;
        }

        if ($this->sha256 === '') {
            logger()->error("No SHA256 checksum available for validation of {$name}.");
            return false;
        }

        $path = DOWNLOAD_PATH . "/{$result->filename}";
        $hash = hash_file('sha256', $path);
        if ($hash !== $this->sha256) {
            logger()->error("SHA256 checksum mismatch for {$name}: expected {$this->sha256}, got {$hash}");
            return false;
        }
        logger()->debug("SHA256 checksum validated successfully for {$name}.");
        return true;
    }

    public function checkUpdate(string $name, array $config, ?string $old_version, ArtifactDownloader $downloader): CheckUpdateResult
    {
        $phpver = $downloader->getOption('with-php', '8.5');
        if ($phpver === 'git') {
            // git version: delegate to Git checkUpdate with master branch
            return (new Git())->checkUpdate($name, ['url' => 'https://github.com/php/php-src.git', 'rev' => 'master'], $old_version, $downloader);
        }
        if (($info = $this->fetchPhpReleaseInfo($phpver, $config, $downloader)) === null) {
            // pre-release: delegate to the php-src git tag check
            return (new PhpTag())->checkUpdate($name, ['version' => $phpver], $old_version, $downloader);
        }
        $new_version = $info['version'];
        return new CheckUpdateResult(
            old: $old_version,
            new: $new_version,
            needUpdate: $old_version === null || $new_version !== $old_version,
        );
    }

    public function cacheMatches(string $name, array $config, array $lock_entry, ArtifactDownloader $downloader): bool
    {
        $requested = $downloader->getOption('with-php');
        // No explicit version request (option left at its null default): accept whatever
        // is cached. The '8.5' default only applies when actually fetching, so a sticky
        // cache is never invalidated by a version the user did not ask about.
        if ($requested === null || $requested === '' || $requested === false) {
            return true;
        }
        $cached_version = $lock_entry['version'] ?? null;
        $cache_type = $lock_entry['cache_type'] ?? null;
        if ($requested === 'git') {
            return $cache_type === 'git';
        }
        return $cached_version !== null
            && $cache_type !== 'git'
            && ($cached_version === $requested || str_starts_with($cached_version, $requested . '.'));
    }

    /** @return array{version: string, url: string, filename: string, sha256: string} */
    protected function resolveRelease(array $info, array $config): array
    {
        $version = $info['version'];
        $filename = null;
        $sha256 = '';
        foreach ($info['source'] ?? [] as $source) {
            if (str_ends_with($source['filename'] ?? '', '.tar.xz')) {
                $sha256 = $source['sha256'] ?? '';
                $filename = $source['filename'];
                break;
            }
        }
        if ($filename === null) {
            throw new DownloaderException("No suitable source tarball found for PHP version {$version}");
        }
        $url = $config['domain'] ?? self::DEFAULT_PHP_DOMAIN;
        $url .= str_replace('{version}', $version, self::DOWNLOAD_URL);
        return ['version' => $version, 'url' => $url, 'filename' => $filename, 'sha256' => $sha256];
    }

    /** @return null|array null when php.net does not publish this version (yet) */
    protected function fetchPhpReleaseInfo(string $phpver, array $config, ArtifactDownloader $downloader): ?array
    {
        $url = $config['domain'] ?? self::DEFAULT_PHP_DOMAIN;
        $url .= self::API_URL;
        $url = str_replace('{version}', $phpver, $url);
        logger()->debug("Fetching PHP release info for version {$phpver} from {$url}");

        // Fetch PHP release info first
        $info = default_shell()->executeCurl($url, retries: $downloader->getRetry());
        if ($info === false) {
            throw new DownloaderException("Failed to fetch PHP release info for version {$phpver}");
        }
        $info = json_decode($info, true);
        if (!is_array($info)) {
            throw new DownloaderException("Invalid PHP release info received for version {$phpver}");
        }
        if (!isset($info['version'])) {
            logger()->debug("php.net has no release for PHP version {$phpver}: " . ($info['error'] ?? 'no version in response'));
            return null;
        }
        return $info;
    }
}
