<?php

declare(strict_types=1);

namespace SPC\store\source;

use SPC\store\Downloader;

class PostgreSQLSource extends CustomSourceBase
{
    public const NAME = 'postgresql';

    public function fetch(bool $force = false, ?array $config = null, int $lock_as = SPC_DOWNLOAD_SOURCE): void
    {
        Downloader::downloadSource('postgresql', self::getLatestInfo(), $force);
    }

    public function getLatestInfo(): array
    {
        [, $filename, $version] = Downloader::getFromFileList('postgresql', [
            'url' => 'https://www.postgresql.org/ftp/source/',
            'regex' => '/href="(?<file>v(?<version>[^"]+)\/)"/',
        ]);
        return [
            'type' => 'url',
            'url' => "https://ftp.postgresql.org/pub/source/{$filename}postgresql-{$version}.tar.gz",
        ];
    }
}
