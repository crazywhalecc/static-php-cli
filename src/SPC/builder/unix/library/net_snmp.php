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
                '--disable-mibs',
                '--without-nl',
                '--disable-agent',
                '--disable-applications',
                '--disable-manuals',
                '--disable-scripts',
                '--disable-embedded-perl',
                '--without-perl-modules',
                '--with-out-mib-modules="if-mib host disman/event-mib ucd-snmp/diskio mibII"',
                '--with-out-transports="Unix"',
                '--with-mib-modules=""',
                '--enable-mini-agent',
                '--with-default-snmp-version="3"',
                '--with-sys-contact="@@no.where"',
                '--with-sys-location="Unknown"',
                '--with-logfile="/var/log/snmpd.log"',
                '--with-persistent-directory="/var/lib/net-snmp"',
                '--with-openssl=' . BUILD_ROOT_PATH,
                '--with-zlib=' . BUILD_ROOT_PATH,
            )->make(with_install: 'installheaders installlibs install_pkgconfig');
        $this->patchPkgconfPrefix();
    }
}
