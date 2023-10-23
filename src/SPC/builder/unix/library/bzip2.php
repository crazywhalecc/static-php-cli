<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait bzip2
{
    protected function build(): void
    {
        $cc = getenv('CC');
        $ar = getenv('AR');
        if ($ar === false) {
            $ar = 'ar';
        }
        shell()->cd($this->source_dir)
            ->exec("make PREFIX='" . BUILD_ROOT_PATH . "' clean")
            ->exec("make -j{$this->builder->concurrency} PREFIX='" . BUILD_ROOT_PATH . "' CC={$cc} AR={$ar} libbz2.a")
            ->exec('cp libbz2.a ' . BUILD_LIB_PATH)
            ->exec('cp bzlib.h ' . BUILD_INCLUDE_PATH);
    }
}
