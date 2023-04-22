<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

class libjpeg extends LinuxLibraryBase
{
    public const NAME = 'libjpeg';

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function build()
    {
        [$lib, $include, $destdir] = SEPARATED_PATH;

        shell()->cd($this->source_dir)
            ->exec(
                <<<EOF
            {$this->builder->configure_env} \\
            cmake -G"Unix Makefiles"   \\
            -DCMAKE_INSTALL_PREFIX=/ \\
            -DCMAKE_INSTALL_LIBDIR={$lib} \\
            -DCMAKE_INSTALL_INCLUDEDIR={$include} \\
            -DCMAKE_BUILD_TYPE=Release  \\
            -DBUILD_SHARED_LIBS=OFF  \\
            -DBUILD_STATIC_LIBS=ON 
EOF
            )
            ->exec("make  -j {$this->builder->concurrency}")
            ->exec('make install')
            ->exec(
                <<<EOF
            rm -rf {$lib}/*.so.*
            rm -rf {$lib}/lib/*.so
            rm -rf {$lib}/lib/*.dylib
EOF
            );
    }
}
