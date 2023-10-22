<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

/**
 * gmp is a template library class for unix
 */
class libmemcached extends MacOSLibraryBase
{
    public const NAME = 'libmemcached';

    public function build(): void
    {
        $rootdir = BUILD_ROOT_PATH;

        shell()->cd($this->source_dir)
            ->exec('chmod +x configure')
            ->exec(
                './configure ' .
                '--enable-static --disable-shared ' .
                '--disable-sasl ' .
                "--prefix={$rootdir}"
            )
            ->exec('make clean')
            ->exec('sed -ie "s/-Werror//g" ' . $this->source_dir . '/Makefile')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install');
    }
}
