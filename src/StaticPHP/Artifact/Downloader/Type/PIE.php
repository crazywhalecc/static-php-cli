<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Exception\DownloaderException;

/** pie */
class PIE implements DownloadTypeInterface
{
    public const string PACKAGIST_URL = 'https://repo.packagist.org/p2/';

    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult
    {
        $packagist_url = self::PACKAGIST_URL . "{$config['repo']}.json";
        logger()->debug("Fetching {$name} source from packagist index: {$packagist_url}");
        $data = default_shell()->executeCurl($packagist_url, retries: $downloader->getRetry());
        if ($data === false) {
            throw new DownloaderException("Failed to fetch packagist index for {$name} from {$packagist_url}");
        }
        $data = json_decode($data, true);
        if (!isset($data['packages'][$config['repo']]) || !is_array($data['packages'][$config['repo']])) {
            throw new DownloaderException("failed to find {$name} repo info from packagist");
        }
        // get the first version
        $first = $data['packages'][$config['repo']][0] ?? [];
        // check 'type' => 'php-ext' or contains 'php-ext' key
        if (!isset($first['php-ext'])) {
            throw new DownloaderException("failed to find {$name} php-ext info from packagist, maybe not a php extension package");
        }
        // get download link from dist
        $dist_url = $first['dist']['url'] ?? null;
        $dist_type = $first['dist']['type'] ?? null;
        if (!$dist_url || !$dist_type) {
            throw new DownloaderException("failed to find {$name} dist info from packagist");
        }
        $name = str_replace('/', '_', $config['repo']);
        $version = $first['version'] ?? 'unknown';
        $filename = "{$name}-{$version}." . ($dist_type === 'zip' ? 'zip' : 'tar.gz');
        $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
        default_shell()->executeCurlDownload($dist_url, $path, retries: $downloader->getRetry());
        return DownloadResult::archive($filename, $config, $config['extract'] ?? null);
    }
}
