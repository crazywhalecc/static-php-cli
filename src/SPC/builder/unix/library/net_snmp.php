<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait net_snmp
{
    protected function build(): void
    {
        // use --static for PKG_CONFIG
        UnixAutoconfExecutor::create($this)
            ->setEnv(['PKG_CONFIG' => getenv('PKG_CONFIG') . ' --static'])
            ->configure(
                '--with-default-snmp-version="3"',
                '--with-sys-contact="@@no.where"',
                '--with-sys-location="Unknown"',
                '--with-logfile="/var/log/snmpd.log"',
                '--with-persistent-directory="/var/ne-snmp"',
                '--with-openssl=' . BUILD_ROOT_PATH,
                '--with-zlib=' . BUILD_ROOT_PATH,
            )->make();
        $this->patchPkgconfPrefix();
    }
}
