<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Exception\DownloaderException;

class PhpRelease implements DownloadTypeInterface, ValidatorInterface
{
    public const string PHP_API = 'https://www.php.net/releases/index.php?json&version={version}';

    public const string DOWNLOAD_URL = 'https://www.php.net/distributions/php-{version}.tar.xz';

    private ?string $sha256 = '';

    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult
    {
        $phpver = $downloader->getOption('with-php', '8.4');
        // Handle 'git' version to clone from php-src repository
        if ($phpver === 'git') {
            $this->sha256 = null;
            return (new Git())->download($name, ['url' => 'https://github.com/php/php-src.git', 'rev' => 'master'], $downloader);
        }

        // Fetch PHP release info first
        $info = default_shell()->executeCurl(str_replace('{version}', $phpver, self::PHP_API), retries: $downloader->getRetry());
        if ($info === false) {
            throw new DownloaderException("Failed to fetch PHP release info for version {$phpver}");
        }
        $info = json_decode($info, true);
        if (!is_array($info) || !isset($info['version'])) {
            throw new DownloaderException("Invalid PHP release info received for version {$phpver}");
        }
        $version = $info['version'];
        foreach ($info['source'] as $source) {
            if (str_ends_with($source['filename'], '.tar.xz')) {
                $this->sha256 = $source['sha256'];
                $filename = $source['filename'];
                break;
            }
        }
        if (!isset($filename)) {
            throw new DownloaderException("No suitable source tarball found for PHP version {$version}");
        }
        $url = str_replace('{version}', $version, self::DOWNLOAD_URL);
        logger()->debug("Downloading PHP release {$version} from {$url}");
        $path = DOWNLOAD_PATH . "/{$filename}";
        default_shell()->executeCurlDownload($url, $path, retries: $downloader->getRetry());
        return DownloadResult::archive($filename, config: $config, extract: $config['extract'] ?? null, version: $version);
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
}
