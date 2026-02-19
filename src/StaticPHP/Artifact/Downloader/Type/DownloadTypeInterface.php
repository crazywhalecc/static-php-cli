<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;

interface DownloadTypeInterface
{
    /**
     * @param string             $name       Download item name
     * @param array              $config     Input configuration for the download
     * @param ArtifactDownloader $downloader Downloader instance
     */
    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult;
}
