<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Exception\DownloaderException;

/** filelist */
class FileList implements DownloadTypeInterface
{
    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult
    {
        logger()->debug("Fetching file list from {$config['url']}");
        $page = default_shell()->executeCurl($config['url'], retries: $downloader->getRetry());
        preg_match_all($config['regex'], $page ?: '', $matches);
        if (!$matches) {
            throw new DownloaderException("Failed to get {$name} file list from {$config['url']}");
        }
        $versions = [];
        logger()->debug('Matched ' . count($matches['version']) . " versions for {$name}");
        foreach ($matches['version'] as $i => $version) {
            $lower = strtolower($version);
            foreach (['alpha', 'beta', 'rc', 'pre', 'nightly', 'snapshot', 'dev'] as $beta) {
                if (str_contains($lower, $beta)) {
                    continue 2;
                }
            }
            $versions[$version] = $matches['file'][$i];
        }
        uksort($versions, 'version_compare');
        $filename = end($versions);
        $version = array_key_last($versions);
        if (isset($config['download-url'])) {
            $url = str_replace(['{file}', '{version}'], [$filename, $version], $config['download-url']);
        } else {
            $url = $config['url'] . $filename;
        }
        $filename = end($versions);
        $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
        logger()->debug("Downloading {$name} from URL: {$url}");
        default_shell()->executeCurlDownload($url, $path, retries: $downloader->getRetry());
        return DownloadResult::archive($filename, $config, $config['extract'] ?? null);
    }
}
