<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Exception\DownloaderException;

/** bitbuckettag */
class BitBucketTag implements DownloadTypeInterface
{
    public const string BITBUCKET_API_URL = 'https://api.bitbucket.org/2.0/repositories/{repo}/refs/tags';

    public const string BITBUCKET_DOWNLOAD_URL = 'https://bitbucket.org/{repo}/get/{version}.tar.gz';

    public function download(string $name, array $config, ArtifactDownloader $downloader): DownloadResult
    {
        logger()->debug("Fetching {$name} API info from bitbucket tag");
        $data = default_shell()->executeCurl(str_replace('{repo}', $config['repo'], self::BITBUCKET_API_URL), retries: $downloader->getRetry());
        $data = json_decode($data ?: '', true);
        $ver = $data['values'][0]['name'] ?? null;
        if (!$ver) {
            throw new DownloaderException("Failed to get {$name} version from BitBucket API");
        }
        $download_url = str_replace(['{repo}', '{version}'], [$config['repo'], $ver], self::BITBUCKET_DOWNLOAD_URL);

        $headers = default_shell()->executeCurl($download_url, method: 'HEAD', retries: $downloader->getRetry());
        preg_match('/^content-disposition:\s+attachment;\s*filename=("?)(?<filename>.+\.tar\.gz)\1/im', $headers, $matches);
        if ($matches) {
            $filename = $matches['filename'];
        } else {
            $filename = "{$name}-{$data['tag_name']}.tar.gz";
        }
        $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
        logger()->debug("Downloading {$name} version {$ver} from BitBucket: {$download_url}");
        default_shell()->executeCurlDownload($download_url, $path, retries: $downloader->getRetry());
        return DownloadResult::archive($filename, $config, extract: $config['extract'] ?? null);
    }
}
