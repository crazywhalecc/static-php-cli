<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\util\executor\UnixAutoconfExecutor;

/**
 * gmp is a template library class for unix
 */
class libmemcached extends MacOSLibraryBase
{
    public const NAME = 'libmemcached';

    public function build(): void
    {
        UnixAutoconfExecutor::create($this)->configure('--disable-sasl')->exec("sed -ie 's/-Werror//g' ./Makefile")->make();
    }
}
