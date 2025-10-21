<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

trait libzip
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->optionalLib('bzip2', ...cmake_boolean_args('ENABLE_BZIP2'))
            ->optionalLib('xz', ...cmake_boolean_args('ENABLE_LZMA'))
            ->optionalLib('openssl', ...cmake_boolean_args('ENABLE_OPENSSL'))
            ->optionalLib('zstd', ...cmake_boolean_args('ENABLE_ZSTD'))
            ->addConfigureArgs(
                '-DENABLE_GNUTLS=OFF',
                '-DENABLE_MBEDTLS=OFF',
                '-DBUILD_DOC=OFF',
                '-DBUILD_EXAMPLES=OFF',
                '-DBUILD_REGRESS=OFF',
                '-DBUILD_TOOLS=OFF',
                '-DBUILD_OSSFUZZ=OFF',
            )
            ->build();
        $this->patchPkgconfPrefix(['libzip.pc'], PKGCONF_PATCH_PREFIX);
    }
}
