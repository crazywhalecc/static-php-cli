<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Library('net-snmp')]
class net_snmp
{
    #[PatchBeforeBuild]
    #[PatchDescription('Link with pthread and dl on Linux')]
    public function patchBeforeBuild(LibraryPackage $lib): void
    {
        spc_skip_if(SystemTarget::getTargetOS() !== 'Linux', 'This patch is only for Linux systems.');
        FileSystem::replaceFileStr("{$lib->getSourceDir()}/configure", 'LIBS="-lssl ${OPENSSL_LIBS}"', 'LIBS="-lssl ${OPENSSL_LIBS} -lpthread -ldl"');
    }

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        // use --static for PKG_CONFIG
        UnixAutoconfExecutor::create($lib)
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
                "--with-openssl={$lib->getBuildRootPath()}",
                "--with-zlib={$lib->getBuildRootPath()}",
            )->make(with_install: 'installheaders installlibs install_pkgconfig');
        $lib->patchPkgconfPrefix();
    }
}
