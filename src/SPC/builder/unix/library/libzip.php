<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\unix\executor\UnixCMakeExecutor;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait libzip
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->optionalLib('bzip2', ...cmake_boolean_args('ENABLE_BZIP2'))
            ->optionalLib('xz', ...cmake_boolean_args('ENABLE_LZMA'))
            ->optionalLib('openssl', ...cmake_boolean_args('ENABLE_OPENSSL'))
            ->addConfigureArgs(
                '-DENABLE_GNUTLS=OFF',
                '-DENABLE_MBEDTLS=OFF',
                '-DBUILD_SHARED_LIBS=OFF',
                '-DBUILD_DOC=OFF',
                '-DBUILD_EXAMPLES=OFF',
                '-DBUILD_REGRESS=OFF',
                '-DBUILD_TOOLS=OFF',
            )
            ->build();
        $this->patchPkgconfPrefix(['libzip.pc'], PKGCONF_PATCH_PREFIX);
    }
}
