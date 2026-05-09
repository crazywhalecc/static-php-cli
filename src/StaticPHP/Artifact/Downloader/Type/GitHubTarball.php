<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Exception\DownloaderException;

/** ghtar */
/** ghtagtar */
class GitHubTarball implements DownloadTypeInterface, CheckUpdateInterface
{
    use GitHubTokenSetupTrait;

    public const string API_URL = 'https://api.github.com/repos/{repo}/{rel_type}';

    private ?string $version = null;

    /**
     * Get the GitHub tarball URL for a given repository and release type.
     * If match_url is provided, only return the tarball that matches the regex.
     */
    public function getGitHubTarballInfo(string $name, string $repo, string $rel_type, bool $prefer_stable = true, ?string $match_url = null, ?string $basename = null, ?string $query = null, int $retries = 0): array
    {
        if ($rel_type === 'releases' && $match_url === null && $query === null && $prefer_stable) {
            $api_url = str_replace(['{repo}', '{rel_type}'], [$repo, 'releases/latest'], self::API_URL);
            $data = default_shell()->executeCurl($api_url, headers: $this->getGitHubTokenHeaders(), retries: $retries);
            $data = json_decode($data ?: '', true);
            if (!is_array($data) || empty($data['tarball_url'])) {
                throw new DownloaderException("Failed to get GitHub latest release for {$repo} from {$api_url}");
            }
            $rel_url = $data['tarball_url'];
            $this->version = $data['tag_name'] ?? $data['name'] ?? null;
        } else {
            $api_url = str_replace(['{repo}', '{rel_type}'], [$repo, $rel_type], self::API_URL);
            $api_url .= ($query ?? '');
            $data = default_shell()->executeCurl($api_url, headers: $this->getGitHubTokenHeaders(), retries: $retries);
            $data = json_decode($data ?: '', true);
            if (!is_array($data)) {
                throw new DownloaderException("Failed to get GitHub tarball URL for {$repo} from {$api_url}");
            }
            $rel_url = null;
            foreach ($data as $rel) {
                $prerelease = $rel['prerelease'] ?? false;
                $draft = $rel['draft'] ?? false;
                $tarball_url = $rel['tarball_url'] ?? null;
                if ($prerelease && $prefer_stable || $draft && $prefer_stable || !$tarball_url) {
                    continue;
                }
                if ($match_url === null) {
                    $rel_url = $rel['tarball_url'] ?? null;
                    $version = $rel['tag_name'] ?? $rel['name'] ?? null;
                    break;
                }
                if (preg_match("|{$match_url}|", $rel['tarball_url'] ?? '')) {
                    $rel_url = $rel['tarball_url'];
                    $version = $rel['tag_name'] ?? $rel['name'] ?? null;
                    break;
                }
            }
            if (!$rel_url) {
                throw new DownloaderException("No suitable GitHub tarball found for {$repo}");
            }
            $this->version = $version ?? null;
        }
        $head = default_shell()->executeCurl($rel_url, 'HEAD', headers: $this->getGitHubTokenHeaders(), retries: $retries) ?: '';
        preg_match('/^content-disposition:\s+attachment;\s*filename=("?)(?<filename>.+\.tar\.gz)\1/im', $head, $matches);
        if ($matches) {
            $filename = $matches['filename'];
        } else {
            $basename = $basename ?? basename($repo);
            $filename = "{$basename}-" . ($this->version ?? 'latest') . '.tar.gz';
        }
        return [$rel_url, $filename];
    }

    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult
    {
        logger()->debug("Downloading GitHub tarball for {$name} from {$config['repo']}");
        $rel_type = match ($config['type']) {
            'ghtar' => 'releases',
            'ghtagtar' => 'tags',
            default => throw new DownloaderException("Invalid GitHubTarball type for {$name}"),
        };
        [$url, $filename] = $this->getGitHubTarballInfo($name, $config['repo'], $rel_type, $config['prefer-stable'] ?? true, $config['match'] ?? null, $name, $config['query'] ?? null, $downloader->getRetry());
        $path = DOWNLOAD_PATH . "/{$filename}";
        default_shell()->executeCurlDownload($url, $path, headers: $this->getGitHubTokenHeaders(), retries: $downloader->getRetry());
        return DownloadResult::archive($filename, $config, $config['extract'] ?? null, version: $this->version, downloader: static::class);
    }

    public function checkUpdate(string $name, array $config, ?string $old_version, ArtifactDownloader $downloader): CheckUpdateResult
    {
        $rel_type = match ($config['type']) {
            'ghtar' => 'releases',
            'ghtagtar' => 'tags',
            default => throw new DownloaderException("Invalid GitHubTarball type for {$name}"),
        };
        $this->getGitHubTarballInfo($name, $config['repo'], $rel_type, $config['prefer-stable'] ?? true, $config['match'] ?? null, $name, $config['query'] ?? null, $downloader->getRetry());
        $new_version = $this->version ?? $old_version ?? '';
        return new CheckUpdateResult(
            old: $old_version,
            new: $new_version,
            needUpdate: $old_version === null || $new_version !== $old_version,
        );
    }
}
