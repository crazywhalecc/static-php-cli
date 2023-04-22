<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

class libwebp extends LinuxLibraryBase
{
    public const NAME = 'libwebp';

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
                 ./autogen.sh 
 EOF
            )
            ->exec(
                <<<EOF
                 {$this->builder->configure_env} \\
                CPPFLAGS="$(pkg-config  --cflags-only-I  --static libpng libjpeg )" \\
                LDFLAGS="$(pkg-config --libs-only-L      --static libpng libjpeg )" \\
                LIBS="$(pkg-config --libs-only-l         --static libpng libjpeg )" \\
                ./configure --prefix=/ \\
                --enable-static --disable-shared \\
                --enable-libwebpdecoder \\
                --enable-libwebpextras \\
                --with-pngincludedir={$include} \\
                --with-pnglibdir={$lib} \\
                --with-jpegincludedir={$include} \\
                --with-jpeglibdir={$lib} \\
                --with-gifincludedir={$include} \\
                --with-giflibdir={$lib} \\
                --disable-tiff  
EOF
            )
            ->exec("make  -j {$this->builder->concurrency}")
            ->exec('make install');
    }
}
