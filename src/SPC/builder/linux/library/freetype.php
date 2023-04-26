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
                <<<'EOF'
            if [[ -d objs/.libs ]] 
            then 
              make clean
            fi 
EOF
            );
        shell()->cd($this->source_dir)
            ->exec(
                <<<EOF
            {$this->builder->configure_env} 
            BZIP2_CFLAGS="-I{$destdir}/include"  \\
            BZIP2_LIBS="-L{$destdir}/lib -lbz2"  \\
            CPPFLAGS="$(pkg-config --cflags-only-I --static zlib libpng  libbrotlidec  libbrotlienc libbrotlicommon)" \\
            LDFLAGS="$(pkg-config  --libs-only-L   --static zlib libpng  libbrotlidec  libbrotlienc libbrotlicommon)" \\
            LIBS="$(pkg-config     --libs-only-l   --static zlib libpng  libbrotlidec  libbrotlienc libbrotlicommon)" \\
            ./configure --prefix={$destdir} \\
            --enable-static \\
            --disable-shared \\
            --with-zlib=yes \\
            --with-bzip2=yes \\
            --with-png=yes \\
            --with-harfbuzz=no  \\
            --with-brotli=no
EOF
            )
            ->exec("make  -j {$this->builder->concurrency}")
            ->exec('make install');
    }
}
