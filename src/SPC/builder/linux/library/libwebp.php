<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\exception\RuntimeException;

class libwebp extends LinuxLibraryBase
{
    public const NAME = 'libwebp';

    /**
     * @throws RuntimeException
     */
    public function build()
    {
        [$lib, $include, $destdir] = SEPARATED_PATH;

        shell()->cd($this->source_dir)
            ->exec(
                <<<EOF
               {$this->builder->configure_env} 
                ./autogen.sh 
                CPPFLAGS="$(pkg-config  --cflags-only-I  --static libpng libpng16 libjpeg libturbojpeg)" \\
                LDFLAGS="$(pkg-config --libs-only-L      --static libpng libpng16 libjpeg libturbojpeg)" \\
                LIBS="$(pkg-config --libs-only-l         --static libpng libpng16 libjpeg libturbojpeg)" \\
                CFLAGS="-fPIC -fPIE" \\
                ./configure \\
                --prefix={$destdir} \\
                --enable-shared=no \\
                --enable-static=yes \\
                --disable-shared \\
                --enable-libwebpdecoder \\
                --enable-libwebpextras \\
                --disable-tiff   \\
                --disable-gl  \\
                --disable-sdl \\
                --disable-wic
EOF
            )
            ->exec($this->builder->configure_env . "make  -j {$this->builder->concurrency}")
            ->exec('make install');
    }
}
