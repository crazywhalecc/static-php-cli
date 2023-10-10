<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;

trait libwebp
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function build(): void
    {
        // CMake needs a clean build directory
        FileSystem::resetDir($this->source_dir . '/build');
        // Start build
        shell()->cd($this->source_dir . '/build')
            ->exec(
                "{$this->builder->configure_env} cmake " .
                $this->builder->makeCmakeArgs() . ' ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DWEBP_BUILD_EXTRAS=ON ' .
                '..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        // patch pkgconfig
        FileSystem::replaceFileRegex(
            BUILD_LIB_PATH . '/pkgconfig/libwebp.pc',
            '/Libs: -L\$\{libdir} -lwebp.*/',
            'Libs: -L${libdir} -lwebp -lwebpdecoder -lwebpdemux -lwebpmux -lsharpyuv'
        );
        $this->patchPkgconfPrefix(['libsharpyuv.pc', 'libwebp.pc', 'libwebpdecoder.pc', 'libwebpdemux.pc', 'libwebpmux.pc'], PKGCONF_PATCH_PREFIX | PKGCONF_PATCH_LIBDIR);
        $this->patchPkgconfPrefix(['libsharpyuv.pc'], PKGCONF_PATCH_CUSTOM, ['/^includedir=.*$/m', 'includedir=${prefix}/include/webp']);
        $this->cleanLaFiles();
    }
}
