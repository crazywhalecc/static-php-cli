<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;

interface ValidatorInterface
{
    public function validate(string $name, array $config, ArtifactDownloader $downloader, DownloadResult $result): bool;
}
