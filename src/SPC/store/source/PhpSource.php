<?php

declare(strict_types=1);

namespace SPC\store\source;

use JetBrains\PhpStorm\ArrayShape;
use SPC\exception\DownloaderException;
use SPC\exception\SPCException;
use SPC\store\Downloader;
use SPC\store\LockFile;

class PhpSource extends CustomSourceBase
{
    public const string NAME = 'php-src';

    public const array WEB_PHP_DOMAINS = [
        'https://www.php.net',
        'https://phpmirror.static-php.dev',
    ];

    public function fetch(bool $force = false, ?array $config = null, int $lock_as = SPC_DOWNLOAD_SOURCE): void
    {
        $source_name = $config['source_name'] ?? 'php-src';

        // Try to extract version from source name (e.g., "php-src-8.2" -> "8.2")
        if (preg_match('/^php-src-([\d.]+)$/', $source_name, $matches)) {
            $major = $matches[1];
        } else {
            $major = defined('SPC_BUILD_PHP_VERSION') ? SPC_BUILD_PHP_VERSION : '8.4';
        }

        if ($major === 'git') {
            Downloader::downloadSource($source_name, ['type' => 'git', 'url' => 'https://github.com/php/php-src.git', 'rev' => 'master'], $force);
        } else {
            Downloader::downloadSource($source_name, $this->getLatestPHPInfo($major), $force);
        }

        if ($source_name !== 'php-src') {
            LockFile::put('php-src', LockFile::get($source_name));
        }
    }

    public function update(array $lock, ?array $config = null): ?array
    {
        $source_name = $config['source_name'] ?? 'php-src';

        // Try to extract version from source name (e.g., "php-src-8.2" -> "8.2")
        if (preg_match('/^php-src-([\d.]+)$/', $source_name, $matches)) {
            $major = $matches[1];
        } else {
            $major = defined('SPC_BUILD_PHP_VERSION') ? SPC_BUILD_PHP_VERSION : '8.4';
        }

        if ($major === 'git') {
            return null;
        }

        $latest_php = $this->getLatestPHPInfo($major);
        $latest_url = $latest_php['url'];
        $filename = basename($latest_url);

        return [$latest_url, $filename];
    }

    /**
     * 获取 PHP x.y 的具体版本号，例如通过 8.1 来获取 8.1.10
     */
    #[ArrayShape(['type' => 'string', 'path' => 'string', 'rev' => 'string', 'url' => 'string'])]
    public function getLatestPHPInfo(string $major_version): array
    {
        foreach (self::WEB_PHP_DOMAINS as $domain) {
            try {
                $info = json_decode(Downloader::curlExec(
                    url: "{$domain}/releases/index.php?json&version={$major_version}",
                    retries: (int) getenv('SPC_DOWNLOAD_RETRIES') ?: 0
                ), true);
                if (!isset($info['version'])) {
                    throw new DownloaderException("Version {$major_version} not found.");
                }
                $version = $info['version'];
                return [
                    'type' => 'url',
                    'url' => "{$domain}/distributions/php-{$version}.tar.xz",
                ];
            } catch (SPCException) {
                logger()->warning('Failed to fetch latest PHP version for major version {$major_version} from {$domain}, trying next mirror if available.');
                continue;
            }
        }
        // exception if all mirrors failed
        throw new DownloaderException("Failed to fetch latest PHP version for major version {$major_version} from all tried mirrors.");
    }
}
