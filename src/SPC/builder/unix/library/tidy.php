<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait tidy
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        $extra = '';
        if (version_compare(get_cmake_version(), '4.0.0', '>=')) {
            $extra .= '-DCMAKE_POLICY_VERSION_MINIMUM=3.5';
        }
        FileSystem::resetDir($this->source_dir . '/build-dir');
        shell()->cd($this->source_dir . '/build-dir')
            ->exec("cmake {$this->builder->makeCmakeArgs()} {$extra} -DSUPPORT_CONSOLE_APP=OFF ..")
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install');
        $this->patchPkgconfPrefix(['tidy.pc']);
    }
}
