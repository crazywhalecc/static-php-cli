<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixAutoconfExecutor;
use SPC\util\SPCTarget;

trait ldap
{
    public function patchBeforeBuild(): bool
    {
        $extra = SPCTarget::getLibc() === 'glibc' ? '-ldl -lpthread -lm -lresolv -lutil' : '';
        FileSystem::replaceFileStr($this->source_dir . '/configure', '"-lssl -lcrypto', '"-lssl -lcrypto -lz ' . $extra);
        return true;
    }

    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->optionalLib('openssl', '--with-tls=openssl')
            ->optionalLib('gmp', '--with-mp=gmp')
            ->optionalLib('libsodium', '--with-argon2=libsodium', '--enable-argon2=no')
            ->addConfigureArgs(
                '--disable-slapd',
                '--without-systemd',
                '--without-cyrus-sasl',
                'ac_cv_func_pthread_kill_other_threads_np=no'
            )
            ->appendEnv([
                'CFLAGS' => '-Wno-date-time',
                'LDFLAGS' => "-L{$this->getLibDir()}",
                'CPPFLAGS' => "-I{$this->getIncludeDir()}",
            ])
            ->configure()
            ->exec('sed -i -e "s/SUBDIRS= include libraries clients servers tests doc/SUBDIRS= include libraries clients servers/g" Makefile')
            ->make();

        FileSystem::replaceFileLineContainsString(BUILD_LIB_PATH . '/pkgconfig/ldap.pc', 'Libs: -L${libdir} -lldap', 'Libs: -L${libdir} -lldap -llber');
        $this->patchPkgconfPrefix(['ldap.pc', 'lber.pc']);
        $this->patchLaDependencyPrefix();
    }
}
