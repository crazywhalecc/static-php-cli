<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;

/** url */
class Url implements DownloadTypeInterface
{
    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult
    {
        $url = $config['url'];
        $filename = $config['filename'] ?? basename($url);
        $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
        logger()->debug("Downloading {$name} from URL: {$url}");
        $version = $config['version'] ?? null;
        default_shell()->executeCurlDownload($url, $path, retries: $downloader->getRetry());
        return DownloadResult::archive($filename, config: $config, extract: $config['extract'] ?? null, version: $version);
    }
}
