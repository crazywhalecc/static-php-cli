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
        $major = defined('SPC_BUILD_PHP_VERSION') ? SPC_BUILD_PHP_VERSION : '8.4';
        if ($major === '8.5') {
            Downloader::downloadSource('php-src', ['type' => 'url', 'url' => 'https://downloads.php.net/~daniels/php-8.5.0RC3.tar.xz'], $force);
        } elseif ($major === 'git') {
            Downloader::downloadSource('php-src', ['type' => 'git', 'url' => 'https://github.com/php/php-src.git', 'rev' => 'master'], $force);
        } else {
            Downloader::downloadSource('php-src', $this->getLatestPHPInfo($major), $force);
        }
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
