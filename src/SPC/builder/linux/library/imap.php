<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

class imap extends LinuxLibraryBase
{
    public const NAME = 'imap';

    /**
     * @throws FileSystemException
     */
    public function patchBeforeBuild(): bool
    {
        $cc = getenv('CC') ?: 'gcc';
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
        shell()->cd($this->source_dir)
            ->exec('make clean')
            ->exec('touch ip6')
            ->exec('chmod +x tools/an')
            ->exec('chmod +x tools/ua')
            ->exec('chmod +x src/osdep/unix/drivers')
            ->exec('chmod +x src/osdep/unix/mkauths')
            ->exec(
                "yes | make slx {$ssl_options} EXTRACFLAGS='-fPIC -fpermissive'"
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
