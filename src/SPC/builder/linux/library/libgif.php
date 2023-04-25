<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

class libgif extends LinuxLibraryBase
{
    public const NAME = 'libgif';

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function build()
    {
        [$lib, $include, $destdir] = SEPARATED_PATH;

        shell()->cd($this->source_dir)
            ->exec('test -f libgif.a && make clean')
            ->exec(" {$this->builder->configure_env}  make  -j {$this->builder->concurrency} libgif.a")
            ->exec("cp libgif.a {$destdir}/lib/libgif.a")
            ->exec("cp gif_lib.h {$destdir}/include/gif_lib.h");
    }
}
