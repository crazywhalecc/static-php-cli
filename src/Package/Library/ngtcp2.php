<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;

#[Library('ngtcp2')]
class ngtcp2
{
    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        WindowsCMakeExecutor::create($lib)
            ->addConfigureArgs(
                '-DENABLE_SHARED_LIB=OFF',
                '-DENABLE_STATIC_LIB=ON',
                '-DBUILD_STATIC_LIBS=ON',
                '-DBUILD_SHARED_LIBS=OFF',
                '-DENABLE_STATIC_CRT=ON',
                '-DENABLE_LIB_ONLY=ON',
                '-DENABLE_OPENSSL=ON',
            )
            ->build();
    }

    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->optionalPackage(
                'openssl',
                fn (LibraryPackage $openssl) => implode(' ', [
                    '--with-openssl=yes',
                    "OPENSSL_LIBS=\"{$openssl->getStaticLibFiles()}\"",
                    "OPENSSL_CFLAGS=\"-I{$openssl->getIncludeDir()}\"",
                ]),
                '--with-openssl=no'
            )
            ->appendEnv(['PKG_CONFIG' => '$PKG_CONFIG --static'])
            ->configure('--enable-lib-only')
            ->make();

        $lib->patchPkgconfPrefix(['libngtcp2.pc', 'libngtcp2_crypto_ossl.pc'], PKGCONF_PATCH_PREFIX);

        // On macOS, the static library may contain other static libraries
        // ld: archive member 'libssl.a' not a mach-o file in libngtcp2_crypto_ossl.a
        $AR = getenv('AR') ?: 'ar';
        shell()->cd($lib->getLibDir())->exec("{$AR} -t libngtcp2_crypto_ossl.a | grep '\\.a\$' | xargs -n1 {$AR} d libngtcp2_crypto_ossl.a");
    }
}
