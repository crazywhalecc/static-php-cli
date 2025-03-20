<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait mimalloc
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        $args = '';
        if (getenv('SPC_LIBC') === 'musl') {
            $args .= '-DMI_LIBC_MUSL=ON ';
        }
        $args .= '-DMI_BUILD_SHARED=OFF ';
        $args .= '-DMI_INSTALL_TOPLEVEL=ON ';
        FileSystem::resetDir($this->source_dir . '/build');
        shell()->cd($this->source_dir . '/build')
            ->exec(
                'cmake ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '-DCMAKE_BUILD_TYPE=Release ' .
                $args .
                '..'
            )
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install');
    }
}
