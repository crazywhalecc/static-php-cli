<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait gettext
{
    protected function build(): void
    {
        $extra = $this->builder->getLib('ncurses') ? ('--with-libncurses-prefix=' . BUILD_ROOT_PATH . ' ') : '';
        $extra .= $this->builder->getLib('libxml2') ? ('--with-libxml2-prefix=' . BUILD_ROOT_PATH . ' ') : '';
        shell()->cd($this->source_dir)
            ->exec(
                './configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                '--disable-java ' .
                $extra .
                '--with-libiconv-prefix=' . BUILD_ROOT_PATH . ' ' .
                '--prefix=' . BUILD_ROOT_PATH
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install');
    }
}
