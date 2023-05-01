<?php

declare(strict_types=1);

namespace SPC\store\source;

use SPC\store\Downloader;

class PostgreSQLSource extends CustomSourceBase
{
    public const NAME = 'postgresql';

    public function fetch()
    {
        Downloader::downloadSource('postgresql', self::getLatestInfo());
    }

    public function getLatestInfo(): array
    {
        [$url, $filename, $version] = Downloader::getFromFileList('postgresql', [
            'url' => 'https://www.postgresql.org/ftp/source/',
            'regex' => '/href="(?<file>v(?<version>[^"]+)\/)"/',
        ]);
        return [
            'type' => 'url',
            'url' => "https://ftp.postgresql.org/pub/source/{$filename}postgresql-{$version}.tar.gz",
        ];
    }
}
