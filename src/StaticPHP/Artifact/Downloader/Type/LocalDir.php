<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;

/** local */
class LocalDir implements DownloadTypeInterface
{
    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult
    {
        logger()->debug("Using local source directory for {$name} from {$config['dirname']}");
        return DownloadResult::local($config['dirname'], $config, extract: $config['extract'] ?? null);
    }
}
