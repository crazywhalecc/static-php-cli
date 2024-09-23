<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait libuuid
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        FileSystem::resetDir($this->source_dir . '/build');
        shell()->cd($this->source_dir . '/build')
            ->exec(
                'cmake ' .
                "{$this->builder->makeCmakeArgs()} " .
                '..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}");
        copy($this->source_dir . '/build/libuuid.a', BUILD_LIB_PATH . '/libuuid.a');
        FileSystem::createDir(BUILD_INCLUDE_PATH . '/uuid');
        copy($this->source_dir . '/uuid.h', BUILD_INCLUDE_PATH . '/uuid/uuid.h');
        $pc = FileSystem::readFile($this->source_dir . '/uuid.pc.in');
        $pc = str_replace([
            '@prefix@',
            '@exec_prefix@',
            '@libdir@',
            '@includedir@',
            '@LIBUUID_VERSION@',
        ], [
            BUILD_ROOT_PATH,
            '${prefix}',
            '${prefix}/lib',
            '${prefix}/include',
            '1.0.3',
        ], $pc);
        FileSystem::writeFile(BUILD_LIB_PATH . '/pkgconfig/uuid.pc', $pc);
    }
}
