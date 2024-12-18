<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\macos\MacOSBuilder;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait libheif
{
    public function patchBeforeBuild(): bool
    {
        if ($this->builder instanceof MacOSBuilder && !str_contains(file_get_contents($this->source_dir . '/CMakeLists.txt'), 'libbrotlienc')) {
            FileSystem::replaceFileStr(
                $this->source_dir . '/CMakeLists.txt',
                'list(APPEND REQUIRES_PRIVATE "libbrotlidec")',
                'list(APPEND REQUIRES_PRIVATE "libbrotlidec")' . "\n" . '        list(APPEND REQUIRES_PRIVATE "libbrotlienc")'
            );
            return true;
        }
        return false;
    }
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        // CMake needs a clean build directory
        FileSystem::resetDir($this->source_dir . '/build');
        // Start build
        shell()->cd($this->source_dir . '/build')
            ->exec(
                'cmake ' .
                '--preset=release ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DWITH_EXAMPLES=OFF ' .
                '-DWITH_GDK_PIXBUF=OFF ' .
                '-DBUILD_TESTING=OFF ' .
                '-DWITH_LIBSHARPYUV=ON ' . // optional: libwebp
                '-DENABLE_PLUGIN_LOADING=OFF ' .
                '..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install');
        $this->patchPkgconfPrefix(['libheif.pc']);
    }
}
