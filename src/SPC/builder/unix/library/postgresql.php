<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\store\FileSystem;
use SPC\util\PkgConfigUtil;
use SPC\util\SPCConfigUtil;
use SPC\util\SPCTarget;

trait postgresql
{
    public function patchBeforeBuild(): bool
    {
        // fix aarch64 build on glibc 2.17 (e.g. CentOS 7)
        if (SPCTarget::getLibcVersion() === '2.17' && GNU_ARCH === 'aarch64') {
            try {
                FileSystem::replaceFileStr("{$this->source_dir}/src/port/pg_popcount_aarch64.c", 'HWCAP_SVE', '0');
                FileSystem::replaceFileStr(
                    "{$this->source_dir}/src/port/pg_crc32c_armv8_choose.c",
                    '#if defined(__linux__) && !defined(__aarch64__) && !defined(HWCAP2_CRC32)',
                    '#if defined(__linux__) && !defined(HWCAP_CRC32)'
                );
            } catch (FileSystemException) {
                // allow file not-existence to make it compatible with old and new version
            }
        }
        // skip the test on platforms where libpq infrastructure may be provided by statically-linked libraries
        FileSystem::replaceFileStr("{$this->source_dir}/src/interfaces/libpq/Makefile", 'invokes exit\'; exit 1;', 'invokes exit\';');
        // disable shared libs build
        FileSystem::replaceFileStr(
            "{$this->source_dir}/src/Makefile.shlib",
            [
                '$(LINK.shared) -o $@ $(OBJS) $(LDFLAGS) $(LDFLAGS_SL) $(SHLIB_LINK)',
                '$(INSTALL_SHLIB) $< \'$(DESTDIR)$(pkglibdir)/$(shlib)\'',
                '$(INSTALL_SHLIB) $< \'$(DESTDIR)$(libdir)/$(shlib)\'',
                '$(INSTALL_SHLIB) $< \'$(DESTDIR)$(bindir)/$(shlib)\'',
            ],
            ''
        );
        return true;
    }

    protected function build(): void
    {
        $libs = array_map(fn ($x) => $x->getName(), $this->getDependencies(true));
        $spc = new SPCConfigUtil($this->builder, ['no_php' => true, 'libs_only_deps' => true]);
        $config = $spc->config(libraries: $libs, include_suggest_lib: $this->builder->getOption('with-suggested-libs', false));

        $env_vars = [
            'CFLAGS' => $config['cflags'] . ' -std=c17',
            'CPPFLAGS' => '-DPIC',
            'LDFLAGS' => $config['ldflags'],
            'LIBS' => $config['libs'],
        ];

        if ($ldLibraryPath = getenv('SPC_LD_LIBRARY_PATH')) {
            $env_vars['LD_LIBRARY_PATH'] = $ldLibraryPath;
        }

        FileSystem::resetDir($this->source_dir . '/build');

        // php source relies on the non-private encoding functions in libpgcommon.a
        FileSystem::replaceFileStr(
            "{$this->source_dir}/src/common/Makefile",
            '$(OBJS_FRONTEND): CPPFLAGS += -DUSE_PRIVATE_ENCODING_FUNCS',
            '$(OBJS_FRONTEND): CPPFLAGS += -UUSE_PRIVATE_ENCODING_FUNCS -DFRONTEND',
        );

        // configure
        $shell = shell()->cd("{$this->source_dir}/build")->initializeEnv($this)
            ->appendEnv($env_vars)
            ->exec(
                '../configure ' .
                "--prefix={$this->getBuildRootPath()} " .
                '--enable-coverage=no ' .
                '--with-ssl=openssl ' .
                '--with-readline ' .
                '--with-libxml ' .
                ($this->builder->getLib('icu') ? '--with-icu ' : '--without-icu ') .
                ($this->builder->getLib('ldap') ? '--with-ldap ' : '--without-ldap ') .
                ($this->builder->getLib('libxslt') ? '--with-libxslt ' : '--without-libxslt ') .
                ($this->builder->getLib('zstd') ? '--with-zstd ' : '--without-zstd ') .
                '--without-lz4 ' .
                '--without-perl ' .
                '--without-python ' .
                '--without-pam ' .
                '--without-bonjour ' .
                '--without-tcl '
            );

        // patch ldap lib
        if ($this->builder->getLib('ldap')) {
            $libs = PkgConfigUtil::getLibsArray('ldap');
            $libs = clean_spaces(implode(' ', $libs));
            FileSystem::replaceFileStr($this->source_dir . '/build/config.status', '-lldap', $libs);
            FileSystem::replaceFileStr($this->source_dir . '/build/src/Makefile.global', '-lldap', $libs);
        }

        $shell
            ->exec('make -C src/bin/pg_config install')
            ->exec('make -C src/include install')
            ->exec('make -C src/common install')
            ->exec('make -C src/port install')
            ->exec('make -C src/interfaces/libpq install');

        // remove dynamic libs
        shell()->cd($this->source_dir . '/build')
            ->exec("rm -rf {$this->getBuildRootPath()}/lib/*.so.*")
            ->exec("rm -rf {$this->getBuildRootPath()}/lib/*.so")
            ->exec("rm -rf {$this->getBuildRootPath()}/lib/*.dylib");

        FileSystem::replaceFileStr("{$this->getLibDir()}/pkgconfig/libpq.pc", '-lldap', '-lldap -llber');
    }
}
