<?php

declare(strict_types=1);

namespace SPC\store\source;

use JetBrains\PhpStorm\ArrayShape;
use SPC\exception\DownloaderException;
use SPC\exception\FileSystemException;
use SPC\store\Downloader;

class PhpSource extends CustomSourceBase
{
    public const NAME = 'php-src';

    /**
     * @throws DownloaderException
     * @throws FileSystemException
     */
    public function fetch(bool $force = false, ?array $config = null, int $lock_as = SPC_LOCK_SOURCE): void
    {
        $major = defined('SPC_BUILD_PHP_VERSION') ? SPC_BUILD_PHP_VERSION : '8.3';
        Downloader::downloadSource('php-src', self::getLatestPHPInfo($major), $force);
    }

    /**
     * 获取 PHP x.y 的具体版本号，例如通过 8.1 来获取 8.1.10
     *
     * @throws DownloaderException
     */
    #[ArrayShape(['type' => 'string', 'path' => 'string', 'rev' => 'string', 'url' => 'string'])]
    public function getLatestPHPInfo(string $major_version): array
    {
        // 查找最新的小版本号
        $info = json_decode(Downloader::curlExec(
            url: "https://www.php.net/releases/index.php?json&version={$major_version}",
            retry: intval(getenv('SPC_RETRY_TIME') ? getenv('SPC_RETRY_TIME') : 0)
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
