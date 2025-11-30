<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Exception\DownloaderException;

/** ghtar */
/** ghtagtar */
class GitHubTarball implements DownloadTypeInterface
{
    use GitHubTokenSetupTrait;

    public const string API_URL = 'https://api.github.com/repos/{repo}/{rel_type}';

    private ?string $version = null;

    /**
     * Get the GitHub tarball URL for a given repository and release type.
     * If match_url is provided, only return the tarball that matches the regex.
     * Otherwise, return the first tarball found.
     */
    public function getGitHubTarballInfo(string $name, string $repo, string $rel_type, bool $prefer_stable = true, ?string $match_url = null, ?string $basename = null): array
    {
        $url = str_replace(['{repo}', '{rel_type}'], [$repo, $rel_type], self::API_URL);
        $data = default_shell()->executeCurl($url, headers: $this->getGitHubTokenHeaders());
        $data = json_decode($data ?: '', true);
        if (!is_array($data)) {
            throw new DownloaderException("Failed to get GitHub tarball URL for {$repo} from {$url}");
        }
        $url = null;
        foreach ($data as $rel) {
            if (($rel['prerelease'] ?? false) === true && $prefer_stable) {
                continue;
            }
            if ($match_url === null) {
                $url = $rel['tarball_url'] ?? null;
                $version = $rel['tag_name'] ?? null;
                break;
            }
            if (preg_match("|{$match_url}|", $rel['tarball_url'] ?? '')) {
                $url = $rel['tarball_url'];
                $version = $rel['tag_name'] ?? null;
                break;
            }
        }
        if (!$url) {
            throw new DownloaderException("No suitable GitHub tarball found for {$repo}");
        }
        $this->version = $version ?? null;
        $head = default_shell()->executeCurl($url, 'HEAD', headers: $this->getGitHubTokenHeaders()) ?: '';
        preg_match('/^content-disposition:\s+attachment;\s*filename=("?)(?<filename>.+\.tar\.gz)\1/im', $head, $matches);
        if ($matches) {
            $filename = $matches['filename'];
        } else {
            $basename = $basename ?? basename($repo);
            $filename = "{$basename}-" . ($rel_type === 'releases' ? $data['tag_name'] : $data['name']) . '.tar.gz';
        }
        return [$url, $filename];
    }

    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult
    {
        logger()->debug("Downloading GitHub tarball for {$name} from {$config['repo']}");
        $rel_type = match ($config['type']) {
            'ghtar' => 'releases',
            'ghtagtar' => 'tags',
            default => throw new DownloaderException("Invalid GitHubTarball type for {$name}"),
        };
        [$url, $filename] = $this->getGitHubTarballInfo($name, $config['repo'], $rel_type, $config['prefer-stable'] ?? true, $config['match'] ?? null, $name);
        $path = DOWNLOAD_PATH . "/{$filename}";
        default_shell()->executeCurlDownload($url, $path, headers: $this->getGitHubTokenHeaders());
        return DownloadResult::archive($filename, $config, $config['extract'] ?? null, version: $this->version);
    }
}
