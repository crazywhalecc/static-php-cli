<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait zstd
{
    protected function build()
    {
        $extra = ' -DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' ';
        $extra .= ' -DCMAKE_BUILD_TYPE=Release ';
        $extra .= ' -DCMAKE_POLICY_DEFAULT_CMP0074=NEW ';
        $extra .= ' -DCMAKE_BUILD_TYPE=Release ';
        $extra .= ' -DBUILD_SHARED_LIBS=OFF ';
        $extra .= ' -DZSTD_BUILD_STATIC=ON ';
        $extra .= ' -DZSTD_BUILD_SHARED=OFF ';
        $extra .= ' -DZSTD_BUILD_CONTRIB=ON ';
        $extra .= ' -DZSTD_BUILD_PROGRAMS=ON ';
        $extra .= ' -DZSTD_BUILD_TESTS=OFF ';
        $extra .= ' -DZSTD_LEGACY_SUPPORT=ON ';
        $extra .= ' -DZSTD_MULTITHREAD_SUPPORT=ON ';
        $extra .= ' -DZSTD_BUILD_PROGRAMS=ON ';

        // lib:zlib
        if ($this->builder->getLib('zlib')) {
            $extra .= ' -DZSTD_ZLIB_SUPPORT=ON -DZLIB_ROOT=' . BUILD_ROOT_PATH . ' ';
        } else {
            $extra .= ' -DZSTD_ZLIB_SUPPORT=OFF ';
        }
        // lib:lzma
        if ($this->builder->getLib('xz')) {
            $extra .= '  -DZSTD_LZMA_SUPPORT=ON -DLibLZMA_ROOT=' . BUILD_ROOT_PATH . ' ';
        } else {
            $extra .= ' -DZSTD_LZMA_SUPPORT=OFF ';
        }
        // lib:LZ4
        if ($this->builder->getLib('liblz4')) {
            $extra .= ' -DZSTD_LZ4_SUPPORT=ON -DLibLZ4_ROOT=' . BUILD_ROOT_PATH . ' ';
        } else {
            $extra .= ' -DZSTD_LZ4_SUPPORT=OFF ';
        }

        FileSystem::resetDir($this->source_dir . '/build/cmake/build_dir');
        shell()->cd($this->source_dir . '/build/cmake/build_dir')
            ->exec(
                "{$this->builder->configure_env} cmake  .. " .
                $extra
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install');
    }
}
