<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait libiconv
{
    protected function build()
    {
        [,,$destdir] = SEPARATED_PATH;

        shell()->cd($this->source_dir)
            ->exec(
                "{$this->builder->configure_env} ./configure " .
                '--enable-static ' .
                '--disable-shared ' .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . $destdir);
    }
}
