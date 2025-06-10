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
        ]);
        if ($brotli = $this->builder->getLib('brotli')) {
            /* @phpstan-ignore-next-line */
            $args .= ' --with-libbrotlidec=yes LIBBROTLIDEC_CFLAGS="-I' . BUILD_ROOT_PATH . '/include" LIBBROTLIDEC_LIBS="' . $brotli->getStaticLibFiles() . '"';
            /* @phpstan-ignore-next-line */
            $args .= ' --with-libbrotlienc=yes LIBBROTLIENC_CFLAGS="-I' . BUILD_ROOT_PATH . '/include" LIBBROTLIENC_LIBS="' . $brotli->getStaticLibFiles() . '"';
        }

        [,,$destdir] = SEPARATED_PATH;

        shell()->cd($this->source_dir)->initializeEnv($this)
            ->exec(
                './configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                '--with-pic ' .
                '--enable-lib-only ' .
                $args . ' ' .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec("make install DESTDIR={$destdir}");
        $this->patchPkgconfPrefix(['libnghttp2.pc']);
        $this->patchLaDependencyPrefix();
    }
}
