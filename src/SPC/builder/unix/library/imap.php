<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\SystemUtil;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
use SPC\store\SourcePatcher;

trait imap
{
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

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function build(): void
    {
        if ($this->builder->getOption('enable-zts')) {
            throw new WrongUsageException('ext-imap is not thread safe, do not build it with ZTS builds');
        }
        $distro = match (SystemUtil::getOSRelease()['dist']) {
            'redhat', 'alpine' => 'slx',
            default => 'ldb'
        };
        if ($distro === 'ldb' && !$this->builder->getLib('libpam')) {
            throw new WrongUsageException('ext-imap built on your system requires libpam, please build with --with-libs=libpam');
        }
        shell()->cd($this->source_dir)
            ->exec('touch ip6')
            ->exec(
                "{$this->builder->configure_env} make {$distro} " .
                'EXTRACFLAGS="-fPIC" ' .
                (
                    $this->builder->getLib('openssl') ?
                        ('SPECIALAUTHENTICATORS=ssl SSLTYPE=unix.nopwd SSLINCLUDE=' . BUILD_INCLUDE_PATH . ' SSLLIB=' . BUILD_LIB_PATH)
                        : 'SSLTYPE=none'
                )
            );
        // todo: answer this with y automatically. using SSLTYPE=nopwd creates imap WITH ssl...
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
