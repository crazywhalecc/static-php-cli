<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\builder\linux\SystemUtil;
use SPC\exception\WrongUsageException;

class imap extends LinuxLibraryBase
{
    // patchBeforeBuild()
    use \SPC\builder\unix\library\imap;

    public const NAME = 'imap';

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
            throw new WrongUsageException('Extension [imap] built on your system requires libpam, please build with --with-libs=libpam');
        }

        // ssl support
        $ssl = $this->builder->getLib('openssl') ? ('SPECIALAUTHENTICATORS=ssl SSLTYPE=unix.nopwd SSLINCLUDE=' . BUILD_INCLUDE_PATH . ' SSLLIB=' . BUILD_LIB_PATH) : 'SSLTYPE=none';

        shell()->cd($this->source_dir)
            ->exec('touch ip6')
            ->exec("{$this->builder->configure_env} make {$distro} EXTRACFLAGS='-fPIC' {$ssl}");
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
