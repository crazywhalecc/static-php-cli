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
            ->exec("make  -j {$this->builder->concurrency} libgif.a")
            ->exec(
                <<<EOF
                cp libgif.a {$lib}/libgif.a
                cp gif_lib.h {$include}/gif_lib.h
EOF
            );
    }
}
