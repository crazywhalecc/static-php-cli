<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;
use SPC\store\SourcePatcher;

trait imap
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function patchBeforeBuild(): bool
    {
        if ($this->builder->getLib('openssl')) {
            FileSystem::replaceFileStr($this->source_dir . '/Makefile', '-DMAC_OSX_KLUDGE=1', '');
            FileSystem::replaceFileStr($this->source_dir . '/src/osdep/unix/Makefile', '-lcrypto -lz', '-lcrypto');
            FileSystem::replaceFileStr($this->source_dir . '/src/osdep/unix/Makefile', '-lcrypto', '-lcrypto -lz');
            FileSystem::replaceFileStr(
                $this->source_dir . '/src/osdep/unix/ssl_unix.c',
                "#include <x509v3.h>\n#include <ssl.h>",
                "#include <ssl.h>\n#include <x509v3.h>"
            );
            SourcePatcher::patchFile('1007_openssl1.1_autoverify.patch', $this->source_dir);
            SourcePatcher::patchFile('2014_openssl1.1.1_sni.patch', $this->source_dir);
            FileSystem::replaceFileStr($this->source_dir . '/Makefile', 'SSLINCLUDE=/usr/include/openssl', 'SSLINCLUDE=' . BUILD_INCLUDE_PATH);
            FileSystem::replaceFileStr($this->source_dir . '/Makefile', 'SSLLIB=/usr/lib', 'SSLLIB=' . BUILD_LIB_PATH);
            return true;
        }
        return false;
    }
}
