<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\SourcePatcher;

#[Library('imap')]
class imap
{
    #[PatchBeforeBuild]
    #[PatchDescription('Patch imap build system for Linux and macOS compatibility')]
    public function patchBeforeBuild(LibraryPackage $lib): void
    {
        if (SystemTarget::getTargetOS() === 'Linux') {
            $cc = getenv('CC') ?: 'gcc';
            // FileSystem::replaceFileStr($lib->getSourceDir() . '/Makefile', '-DMAC_OSX_KLUDGE=1', '');
            FileSystem::replaceFileStr("{$lib->getSourceDir()}/src/osdep/unix/Makefile", 'CC=cc', "CC={$cc}");
            /* FileSystem::replaceFileStr($lib->getSourceDir() . '/src/osdep/unix/Makefile', '-lcrypto -lz', '-lcrypto');
            FileSystem::replaceFileStr($lib->getSourceDir() . '/src/osdep/unix/Makefile', '-lcrypto', '-lcrypto -lz');
            FileSystem::replaceFileStr(
                $lib->getSourceDir() . '/src/osdep/unix/ssl_unix.c',
                "#include <x509v3.h>\n#include <ssl.h>",
                "#include <ssl.h>\n#include <x509v3.h>"
            );
            // SourcePatcher::patchFile('1006_openssl1.1_autoverify.patch', $lib->getSourceDir());
            SourcePatcher::patchFile('2014_openssl1.1.1_sni.patch', $lib->getSourceDir()); */
            FileSystem::replaceFileStr("{$lib->getSourceDir()}/Makefile", 'SSLINCLUDE=/usr/include/openssl', "SSLINCLUDE={$lib->getIncludeDir()}");
            FileSystem::replaceFileStr("{$lib->getSourceDir()}/Makefile", 'SSLLIB=/usr/lib', "SSLLIB={$lib->getLibDir()}");
        } elseif (SystemTarget::getTargetOS() === 'Darwin') {
            $cc = getenv('CC') ?: 'clang';
            SourcePatcher::patchFile('0001_imap_macos.patch', $lib->getSourceDir());
            FileSystem::replaceFileStr($lib->getSourceDir() . '/src/osdep/unix/Makefile', 'CC=cc', "CC={$cc}");
            FileSystem::replaceFileStr($lib->getSourceDir() . '/Makefile', 'SSLINCLUDE=/usr/include/openssl', 'SSLINCLUDE=' . $lib->getIncludeDir());
            FileSystem::replaceFileStr($lib->getSourceDir() . '/Makefile', 'SSLLIB=/usr/lib', 'SSLLIB=' . $lib->getLibDir());
        }
    }

    #[BuildFor('Linux')]
    public function buildLinux(LibraryPackage $lib, PackageInstaller $installer): void
    {
        if ($installer->isPackageResolved('openssl')) {
            $ssl_options = "SPECIALAUTHENTICATORS=ssl SSLTYPE=unix.nopwd SSLINCLUDE={$lib->getIncludeDir()} SSLLIB={$lib->getLibDir()}";
        } else {
            $ssl_options = 'SSLTYPE=none';
        }
        $libcVer = SystemTarget::getLibcVersion();
        $extraLibs = $libcVer && version_compare($libcVer, '2.17', '<=') ? 'EXTRALDFLAGS="-ldl -lrt -lpthread"' : '';
        try {
            shell()->cd($lib->getSourceDir())
                ->exec('make clean')
                ->exec('touch ip6')
                ->exec('chmod +x tools/an')
                ->exec('chmod +x tools/ua')
                ->exec('chmod +x src/osdep/unix/drivers')
                ->exec('chmod +x src/osdep/unix/mkauths')
                // PASSWDTYPE=nul avoids any crypt() symbol reference in c-client.a;
                // zig-cc 0.15+ uses paths_first strategy and cannot find libcrypt outside of buildroot.
                ->exec("yes | make slx {$ssl_options} PASSWDTYPE=nul EXTRACFLAGS='-fPIC -Wno-implicit-function-declaration -Wno-incompatible-function-pointer-types' {$extraLibs}");
        } catch (\Throwable $e) {
            // slx target also builds bundled tools (mtest, etc.) which may fail to link -lcrypt dynamically
            // (e.g. with zig-cc). We only need c-client.a, so tolerate the failure if it was built.
            if (!file_exists("{$lib->getSourceDir()}/c-client/c-client.a")) {
                throw $e;
            }
        }
        try {
            shell()
                ->exec("cp -rf {$lib->getSourceDir()}/c-client/c-client.a {$lib->getLibDir()}/libc-client.a")
                ->exec("cp -rf {$lib->getSourceDir()}/c-client/*.c {$lib->getLibDir()}/")
                ->exec("cp -rf {$lib->getSourceDir()}/c-client/*.h {$lib->getIncludeDir()}/")
                ->exec("cp -rf {$lib->getSourceDir()}/src/osdep/unix/*.h {$lib->getIncludeDir()}/");
        } catch (\Throwable) {
            // last command throws an exception, no idea why since it works
        }
    }

    #[BuildFor('Darwin')]
    public function buildDarwin(LibraryPackage $lib, PackageInstaller $installer): void
    {
        if ($installer->isPackageResolved('openssl')) {
            $ssl_options = "SPECIALAUTHENTICATORS=ssl SSLTYPE=unix.nopwd SSLINCLUDE={$lib->getIncludeDir()} SSLLIB={$lib->getLibDir()}";
        } else {
            $ssl_options = 'SSLTYPE=none';
        }
        $out = shell()->execWithResult('echo "-include $(xcrun --show-sdk-path)/usr/include/poll.h -include $(xcrun --show-sdk-path)/usr/include/time.h -include $(xcrun --show-sdk-path)/usr/include/utime.h"')[1][0];
        try {
            shell()->cd($lib->getSourceDir())
                ->exec('make clean')
                ->exec('touch ip6')
                ->exec('chmod +x tools/an')
                ->exec('chmod +x tools/ua')
                ->exec('chmod +x src/osdep/unix/drivers')
                ->exec('chmod +x src/osdep/unix/mkauths')
                ->exec(
                    "echo y | make osx {$ssl_options} EXTRACFLAGS='-Wno-implicit-function-declaration -Wno-incompatible-function-pointer-types {$out}'"
                );
        } catch (\Throwable $e) {
            // osx target also builds bundled tools (mtest, etc.) which may fail to link.
            // We only need c-client.a, so tolerate the failure if it was built.
            if (!file_exists("{$lib->getSourceDir()}/c-client/c-client.a")) {
                throw $e;
            }
        }
        try {
            shell()
                ->exec("cp -rf {$lib->getSourceDir()}/c-client/c-client.a {$lib->getLibDir()}/libc-client.a")
                ->exec("cp -rf {$lib->getSourceDir()}/c-client/*.c {$lib->getLibDir()}/")
                ->exec("cp -rf {$lib->getSourceDir()}/c-client/*.h {$lib->getIncludeDir()}/")
                ->exec("cp -rf {$lib->getSourceDir()}/src/osdep/unix/*.h {$lib->getIncludeDir()}/");
        } catch (\Throwable) {
            // last command throws an exception, no idea why since it works
        }
    }
}
