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

#[Library('ldap')]
class ldap
{
    #[PatchBeforeBuild]
    #[PatchDescription('Add zlib and extra libs to linker flags for ldap')]
    public function patchBeforeBuild(LibraryPackage $lib): bool
    {
        $extra = SystemTarget::getLibc() === 'glibc' ? '-ldl -lpthread -lm -lresolv -lutil' : '';
        FileSystem::replaceFileStr($lib->getSourceDir() . '/configure', '"-lssl -lcrypto', '"-lssl -lcrypto -lz ' . $extra);
        return true;
    }

    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->optionalPackage('openssl', '--with-tls=openssl')
            ->optionalPackage('gmp', '--with-mp=gmp')
            ->optionalPackage('libsodium', '--with-argon2=libsodium', '--enable-argon2=no')
            ->addConfigureArgs(
                '--disable-slapd',
                '--without-systemd',
                '--without-cyrus-sasl',
                'ac_cv_func_pthread_kill_other_threads_np=no'
            )
            ->appendEnv([
                'CFLAGS' => '-Wno-date-time',
                'LDFLAGS' => "-L{$lib->getLibDir()}",
                'CPPFLAGS' => "-I{$lib->getIncludeDir()}",
            ])
            ->configure()
            ->exec('sed -i -e "s/SUBDIRS= include libraries clients servers tests doc/SUBDIRS= include libraries clients servers/g" Makefile')
            ->make();

        FileSystem::replaceFileLineContainsString(
            $lib->getLibDir() . '/pkgconfig/ldap.pc',
            'Libs: -L${libdir} -lldap',
            'Libs: -L${libdir} -lldap -llber'
        );
        $lib->patchPkgconfPrefix(['ldap.pc', 'lber.pc']);
        $lib->patchLaDependencyPrefix();
    }
}
