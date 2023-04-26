<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

class libjpeg extends MacOSLibraryBase
{
    public const NAME = 'libjpeg';

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function build()
    {
        [$lib, $include, $destdir] = SEPARATED_PATH;
        shell()
            ->cd($this->source_dir)
            ->exec(
                <<<'EOF'
        if [[ -d build ]] 
        then
           rm -rf build 
        fi
        mkdir -p build 
EOF
            );
        shell()->cd($this->source_dir . '/build/')
            ->exec(
                <<<EOF
            {$this->builder->configure_env} 
            cmake -G"Unix Makefiles"   \\
            ..  \\
            -DCMAKE_INSTALL_PREFIX={$destdir} \\
            -DCMAKE_INSTALL_BINDIR={$destdir}/bin/ \\
            -DCMAKE_INSTALL_LIBDIR={$destdir}/lib \\
            -DCMAKE_INSTALL_INCLUDEDIR={$destdir}/include \\
            -DCMAKE_BUILD_TYPE=Release  \\
            -DENABLE_SHARED=OFF  \\
            -DENABLE_STATIC=ON  
            # -DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} 
         
EOF
            )
            ->exec("make  -j {$this->builder->concurrency}")
            ->exec('make install')
            ->exec(
                <<<EOF
            rm -rf {$destdir}/lib/*.so.*
            rm -rf {$destdir}/lib/*.so
            rm -rf {$destdir}/lib/*.dylib
EOF
            );
    }
}
