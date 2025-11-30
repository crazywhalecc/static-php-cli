<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;

/** git */
class Git implements DownloadTypeInterface
{
    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult
    {
        $path = DOWNLOAD_PATH . "/{$name}";
        logger()->debug("Cloning git repository for {$name} from {$config['url']}");
        $shallow = !$downloader->getOption('no-shallow-clone', false);
        default_shell()->executeGitClone($config['url'], $config['rev'], $path, $shallow, $config['submodules'] ?? null);
        $version = "dev-{$config['rev']}";
        return DownloadResult::git($name, $config, extract: $config['extract'] ?? null, version: $version);
    }
}
