<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Exception\DownloaderException;

/* pecl */
class PECL implements DownloadTypeInterface, CheckUpdateInterface
{
    private const string PECL_BASE_URL = 'https://pecl.php.net';

    /** REST API: returns XML with <r><v>VERSION</v><s>STATE</s></r> per release */
    private const string PECL_REST_URL = 'https://pecl.php.net/rest/r/%s/allreleases.xml';

    public function checkUpdate(string $name, array $config, ?string $old_version, ArtifactDownloader $downloader): CheckUpdateResult
    {
        [, $version] = $this->fetchPECLInfo($name, $config, $downloader);
        return new CheckUpdateResult(
            old: $old_version,
            new: $version,
            needUpdate: $old_version === null || version_compare($version, $old_version, '>'),
        );
    }

    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult
    {
        [$filename, $version] = $this->fetchPECLInfo($name, $config, $downloader);
        $url = self::PECL_BASE_URL . '/get/' . $filename;
        $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
        logger()->debug("Downloading {$name} from URL: {$url}");
        default_shell()->executeCurlDownload($url, $path, retries: $downloader->getRetry());
        $extract = $config['extract'] ?? ('php-src/ext/' . $this->getExtractName($name));
        return DownloadResult::archive($filename, $config, $extract, version: $version, downloader: static::class);
    }

    protected function fetchPECLInfo(string $name, array $config, ArtifactDownloader $downloader): array
    {
        $peclName = strtolower($config['name'] ?? $this->getExtractName($name));
        $url = sprintf(self::PECL_REST_URL, $peclName);
        logger()->debug("Fetching PECL release list for {$name} from REST API");
        $xml = default_shell()->executeCurl($url, retries: $downloader->getRetry());
        if ($xml === false) {
            throw new DownloaderException("Failed to fetch PECL release list for {$name}");
        }
        // Match <r><v>VERSION</v><s>STATE</s></r>
        preg_match_all('/<r><v>(?P<version>[^<]+)<\/v><s>(?P<state>[^<]+)<\/s><\/r>/', $xml, $matches);
        if (empty($matches['version'])) {
            throw new DownloaderException("Failed to parse PECL release list for {$name}");
        }
        $versions = [];
        logger()->debug('Matched ' . count($matches['version']) . " releases for {$name} from PECL");
        foreach ($matches['version'] as $i => $version) {
            if ($matches['state'][$i] !== 'stable') {
                continue;
            }
            $versions[$version] = $peclName . '-' . $version . '.tgz';
        }
        if (empty($versions)) {
            throw new DownloaderException("No stable releases found for {$name} on PECL");
        }
        uksort($versions, 'version_compare');
        $filename = end($versions);
        $version = array_key_last($versions);
        return [$filename, $version, $versions];
    }

    /**
     * Derive the lowercase PECL package / extract name from the artifact name.
     * e.g. "ext-apcu" -> "apcu", "ext-ast" -> "ast"
     */
    private function getExtractName(string $name): string
    {
        return strtolower(preg_replace('/^ext-/i', '', $name));
    }
}
