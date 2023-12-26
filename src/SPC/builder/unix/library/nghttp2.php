<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;

trait nghttp2
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function build(): void
    {
        $args = $this->builder->makeAutoconfArgs(static::NAME, [
            'zlib' => null,
            'openssl' => null,
            'libxml2' => null,
            'libev' => null,
            'libcares' => null,
            'libngtcp2' => null,
            'libnghttp3' => null,
            'libbpf' => null,
            'libevent-openssl' => null,
            'jansson' => null,
            'jemalloc' => null,
            'systemd' => null,
            'cunit' => null,
        ]);

        [,,$destdir] = SEPARATED_PATH;

        shell()->cd($this->source_dir)
            ->exec(
                './configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                '--enable-lib-only ' .
                '--with-boost=no ' .
                $args . ' ' .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec("make install DESTDIR={$destdir}");
        $this->patchPkgconfPrefix(['libnghttp2.pc']);
    }
}
