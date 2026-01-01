<?php

declare(strict_types=1);

namespace SPC\store\source;

use JetBrains\PhpStorm\ArrayShape;
use SPC\exception\DownloaderException;
use SPC\store\Downloader;

class PhpSource extends CustomSourceBase
{
    public const NAME = 'php-src';

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
        // 查找最新的小版本号
        $info = json_decode(Downloader::curlExec(
            url: "https://www.php.net/releases/index.php?json&version={$major_version}",
            retries: (int) getenv('SPC_DOWNLOAD_RETRIES') ?: 0
        ), true);
        if (!isset($info['version'])) {
            throw new DownloaderException("Version {$major_version} not found.");
        }

        $version = $info['version'];

        // 从官网直接下载
        return [
            'type' => 'url',
            'url' => "https://www.php.net/distributions/php-{$version}.tar.xz",
        ];
    }
}
