<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Exception\DownloaderException;

/** ghrel */
class GitHubRelease implements DownloadTypeInterface, ValidatorInterface
{
    use GitHubTokenSetupTrait;

    public const string API_URL = 'https://api.github.com/repos/{repo}/releases';

    public const string ASSET_URL = 'https://api.github.com/repos/{repo}/releases/assets/{id}';

    private string $sha256 = '';

    private ?string $version = null;

    public function getGitHubReleases(string $name, string $repo, bool $prefer_stable = true): array
    {
        logger()->debug("Fetching {$name} GitHub releases from {$repo}");
        $url = str_replace('{repo}', $repo, self::API_URL);
        $headers = $this->getGitHubTokenHeaders();
        $data2 = default_shell()->executeCurl($url, headers: $headers);
        $data = json_decode($data2 ?: '', true);
        if (!is_array($data)) {
            throw new DownloaderException("Failed to get GitHub release API info for {$repo} from {$url}");
        }
        $releases = [];
        foreach ($data as $release) {
            if ($prefer_stable && $release['prerelease'] === true) {
                continue;
            }
            $releases[] = $release;
        }
        return $releases;
    }

    /**
     * Get the latest GitHub release assets for a given repository.
     * match_asset is provided, only return the asset that matches the regex.
     */
    public function getLatestGitHubRelease(string $name, string $repo, bool $prefer_stable, string $match_asset): array
    {
        $url = str_replace('{repo}', $repo, self::API_URL);
        $headers = $this->getGitHubTokenHeaders();
        $data2 = default_shell()->executeCurl($url, headers: $headers);
        $data = json_decode($data2 ?: '', true);
        if (!is_array($data)) {
            throw new DownloaderException("Failed to get GitHub release API info for {$repo} from {$url}");
        }
        foreach ($data as $release) {
            if ($prefer_stable && $release['prerelease'] === true) {
                continue;
            }
            foreach ($release['assets'] as $asset) {
                if (preg_match("|{$match_asset}|", $asset['name'])) {
                    if (isset($asset['id'], $asset['name'])) {
                        // store ghrel asset array (id: ghrel.{$repo}.{stable|unstable}.{$match_asset})
                        if ($asset['digest'] !== null && str_starts_with($asset['digest'], 'sha256:')) {
                            $this->sha256 = substr($asset['digest'], 7);
                        }
                        $this->version = $release['tag_name'] ?? null;
                        return $asset;
                    }
                    throw new DownloaderException("Failed to get asset name and id for {$repo}");
                }
            }
        }
        throw new DownloaderException("No suitable GitHub release found for {$repo}");
    }

    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult
    {
        logger()->debug("Fetching GitHub release for {$name} from {$config['repo']}");
        if (!isset($config['match'])) {
            throw new DownloaderException("GitHubRelease downloader requires 'match' config for {$name}");
        }
        $rel = $this->getLatestGitHubRelease($name, $config['repo'], $config['prefer-stable'] ?? true, $config['match']);

        // download file using curl
        $asset_url = str_replace(['{repo}', '{id}'], [$config['repo'], $rel['id']], self::ASSET_URL);
        $headers = array_merge(
            $this->getGitHubTokenHeaders(),
            ['Accept: application/octet-stream']
        );
        $filename = $rel['name'];
        $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
        logger()->debug("Downloading {$name} asset from URL: {$asset_url}");
        default_shell()->executeCurlDownload($asset_url, $path, headers: $headers, retries: $downloader->getRetry());
        return DownloadResult::archive($filename, $config, extract: $config['extract'] ?? null, version: $this->version);
    }

    public function validate(string $name, array $config, ArtifactDownloader $downloader, DownloadResult $result): bool
    {
        if ($result->cache_type != 'archive') {
            logger()->warning("GitHub release validator only supports archive download type for {$name} .");
            return false;
        }

        if ($this->sha256 !== '') {
            $calculated_hash = hash_file('sha256', DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $result->filename);
            if ($this->sha256 !== $calculated_hash) {
                logger()->error("Hash mismatch for downloaded GitHub release asset of {$name}: expected {$this->sha256}, got {$calculated_hash}");
                return false;
            }
            logger()->debug("Hash verified for downloaded GitHub release asset of {$name}");
            return true;
        }
        logger()->debug("No sha256 digest found for GitHub release asset of {$name}, skipping hash validation");
        return true;
    }
}
