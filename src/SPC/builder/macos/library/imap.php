<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;
use SPC\store\SourcePatcher;

class imap extends MacOSLibraryBase
{
    public const NAME = 'imap';

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function patchBeforeBuild(): bool
    {
        $cc = getenv('CC') ?: 'clang';
        SourcePatcher::patchFile('0001_imap_macos.patch', $this->source_dir);
        // FileSystem::replaceFileStr($this->source_dir . '/Makefile', '-DMAC_OSX_KLUDGE=1', '');
        FileSystem::replaceFileStr($this->source_dir . '/src/osdep/unix/Makefile', 'CC=cc', "CC={$cc}");
        /* FileSystem::replaceFileStr($this->source_dir . '/src/osdep/unix/Makefile', '-lcrypto -lz', '-lcrypto');
        FileSystem::replaceFileStr($this->source_dir . '/src/osdep/unix/Makefile', '-lcrypto', '-lcrypto -lz');
        FileSystem::replaceFileStr(
            $this->source_dir . '/src/osdep/unix/ssl_unix.c',
            "#include <x509v3.h>\n#include <ssl.h>",
            "#include <ssl.h>\n#include <x509v3.h>"
        );
        // SourcePatcher::patchFile('1006_openssl1.1_autoverify.patch', $this->source_dir);
        SourcePatcher::patchFile('2014_openssl1.1.1_sni.patch', $this->source_dir); */
        FileSystem::replaceFileStr($this->source_dir . '/Makefile', 'SSLINCLUDE=/usr/include/openssl', 'SSLINCLUDE=' . BUILD_INCLUDE_PATH);
        FileSystem::replaceFileStr($this->source_dir . '/Makefile', 'SSLLIB=/usr/lib', 'SSLLIB=' . BUILD_LIB_PATH);
        return true;
    }

    /**
     * @throws RuntimeException
     */
    protected function build(): void
    {
        if ($this->builder->getLib('openssl')) {
            $ssl_options = 'SPECIALAUTHENTICATORS=ssl SSLTYPE=unix.nopwd SSLINCLUDE=' . BUILD_INCLUDE_PATH . ' SSLLIB=' . BUILD_LIB_PATH;
        } else {
            $ssl_options = 'SSLTYPE=none';
        }
        $out = shell()->execWithResult('echo "-include $(xcrun --show-sdk-path)/usr/include/poll.h -include $(xcrun --show-sdk-path)/usr/include/time.h -include $(xcrun --show-sdk-path)/usr/include/utime.h"')[1][0];
        shell()->cd($this->source_dir)
            ->exec('make clean')
            ->exec('touch ip6')
            ->exec('chmod +x tools/an')
            ->exec('chmod +x tools/ua')
            ->exec('chmod +x src/osdep/unix/drivers')
            ->exec('chmod +x src/osdep/unix/mkauths')
            ->exec(
                "echo y | make osx {$ssl_options} EXTRACFLAGS='-Wno-implicit-function-declaration -Wno-incompatible-function-pointer-types {$out}'"
            );
        try {
            shell()
                ->exec("cp -rf {$this->source_dir}/c-client/c-client.a " . BUILD_LIB_PATH . '/libc-client.a')
                ->exec("cp -rf {$this->source_dir}/c-client/*.c " . BUILD_LIB_PATH . '/')
                ->exec("cp -rf {$this->source_dir}/c-client/*.h " . BUILD_INCLUDE_PATH . '/')
                ->exec("cp -rf {$this->source_dir}/src/osdep/unix/*.h " . BUILD_INCLUDE_PATH . '/');
        } catch (\Throwable) {
            // last command throws an exception, no idea why since it works
        }
    }
}
