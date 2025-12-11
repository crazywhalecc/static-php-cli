<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Exception\DownloaderException;
use StaticPHP\Util\FileSystem;

/** git */
class Git implements DownloadTypeInterface
{
    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult
    {
        $path = DOWNLOAD_PATH . "/{$name}";
        logger()->debug("Cloning git repository for {$name} from {$config['url']}");
        $shallow = !$downloader->getOption('no-shallow-clone', false);

        // direct branch clone
        if (isset($config['rev'])) {
            default_shell()->executeGitClone($config['url'], $config['rev'], $path, $shallow, $config['submodules'] ?? null);
            $version = "dev-{$config['rev']}";
            return DownloadResult::git($name, $config, extract: $config['extract'] ?? null, version: $version);
        }
        if (!isset($config['regex'])) {
            throw new DownloaderException('Either "rev" or "regex" must be specified for git download type.');
        }

        // regex matches branch first, we need to fetch all refs in emptyfirst
        $gitdir = sys_get_temp_dir() . '/' . $name;
        FileSystem::resetDir($gitdir);
        $shell = PHP_OS_FAMILY === 'Windows' ? cmd(false) : shell(false);
        $result = $shell->cd($gitdir)
            ->exec(SPC_GIT_EXEC . ' init')
            ->exec(SPC_GIT_EXEC . ' remote add origin ' . escapeshellarg($config['url']))
            ->execWithResult(SPC_GIT_EXEC . ' ls-remote origin');
        if ($result[0] !== 0) {
            throw new DownloaderException("Failed to ls-remote from {$config['url']}");
        }
        $refs = $result[1];
        $matched_version_branch = [];
        $matched_count = 0;

        $regex = '/^' . $config['regex'] . '$/';
        foreach ($refs as $ref) {
            $matches = null;
            if (preg_match('/^[0-9a-f]{40}\s+refs\/heads\/(.+)$/', $ref, $matches)) {
                ++$matched_count;
                $branch = $matches[1];
                if (preg_match($regex, $branch, $vermatch) && isset($vermatch['version'])) {
                    $matched_version_branch[$vermatch['version']] = $vermatch[0];
                }
            }
        }
        // sort versions
        uksort($matched_version_branch, function ($a, $b) {
            return version_compare($b, $a);
        });
        if (!empty($matched_version_branch)) {
            // use the highest version
            $version = array_key_first($matched_version_branch);
            $branch = $matched_version_branch[$version];
            logger()->info("Matched version {$version} from branch {$branch} for {$name}");
            default_shell()->executeGitClone($config['url'], $branch, $path, $shallow, $config['submodules'] ?? null);
            return DownloadResult::git($name, $config, extract: $config['extract'] ?? null, version: $version);
        }
        throw new DownloaderException("No matching branch found for regex {$config['regex']} (checked {$matched_count} branches).");
    }
}
