<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\exception\RuntimeException;

class freetype extends LinuxLibraryBase
{
    public const NAME = 'freetype';

    /**
     * @throws RuntimeException
     */
    public function build()
    {
        [$lib, $include, $destdir] = SEPARATED_PATH;

        shell()->cd($this->source_dir)
            ->exec(
                <<<EOF
            test -d objs/.libs && make clean
            {$this->builder->configure_env} 
            BZIP2_CFLAGS="-I{$destdir}/include"  \\
            BZIP2_LIBS="-L{$destdir}/lib -lbz2"  \\
            CPPFLAGS="$(pkg-config --cflags-only-I --static zlib libpng  libbrotlicommon  libbrotlidec  libbrotlienc)" \\
            LDFLAGS="$(pkg-config  --libs-only-L   --static zlib libpng  libbrotlicommon  libbrotlidec  libbrotlienc)" \\
            LIBS="$(pkg-config     --libs-only-l   --static zlib libpng  libbrotlicommon  libbrotlidec  libbrotlienc)" \\
            ./configure --prefix={$destdir} \\
            --enable-static \\
            --disable-shared \\
            --with-zlib=yes \\
            --with-bzip2=yes \\
            --with-png=yes \\
            --with-harfbuzz=no  \\
            --with-brotli=yes  
EOF
            )
            ->exec("make  -j {$this->builder->concurrency}")
            ->exec('make install');
    }
}
