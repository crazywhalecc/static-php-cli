<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait libiconv
{
    protected function build(): void
    {
        [,,$destdir] = SEPARATED_PATH;

        shell()->cd($this->source_dir)
            ->exec(
                './configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                '--enable-extra-encodings ' .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . $destdir);

        if (file_exists(BUILD_BIN_PATH . '/iconv')) {
            unlink(BUILD_BIN_PATH . '/iconv');
        }
    }
}
