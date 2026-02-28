<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;

interface CheckUpdateInterface
{
    /**
     * Check if an update is available for the given artifact.
     *
     * @param string             $name        the name of the artifact
     * @param array              $config      the configuration for the artifact
     * @param string             $old_version old version or identifier of the artifact to compare against
     * @param ArtifactDownloader $downloader  the artifact downloader instance
     */
    public function checkUpdate(string $name, array $config, ?string $old_version, ArtifactDownloader $downloader): CheckUpdateResult;
}
