<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait zlib
{
    protected function build()
    {
        [,,$destdir] = SEPARATED_PATH;

        shell()->cd($this->source_dir)
            ->exec("{$this->builder->configure_env} ./configure --static --prefix=")
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec("make install DESTDIR={$destdir}");
        $this->patchPkgconfPrefix(['zlib.pc']);
    }
}
