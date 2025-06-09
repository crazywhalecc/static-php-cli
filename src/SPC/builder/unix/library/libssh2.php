<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\executor\UnixCMakeExecutor;

trait libssh2
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->optionalLib('zlib', ...cmake_boolean_args('ENABLE_ZLIB_COMPRESSION'))
            ->addConfigureArgs(
                '-DBUILD_EXAMPLES=OFF',
                '-DBUILD_TESTING=OFF'
            )
            ->build();

        $this->patchPkgconfPrefix(['libssh2.pc']);
    }
}
