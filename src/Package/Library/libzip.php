<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;

#[Library('libzip')]
class libzip
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        UnixCMakeExecutor::create($lib)
            ->optionalPackage('bzip2', ...cmake_boolean_args('ENABLE_BZIP2'))
            ->optionalPackage('xz', ...cmake_boolean_args('ENABLE_LZMA'))
            ->optionalPackage('openssl', ...cmake_boolean_args('ENABLE_OPENSSL'))
            ->optionalPackage('zstd', ...cmake_boolean_args('ENABLE_ZSTD'))
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
        $lib->patchPkgconfPrefix(['libzip.pc'], PKGCONF_PATCH_PREFIX);
    }
}
