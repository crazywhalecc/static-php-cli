<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\exception\BuildFailureException;
use SPC\store\FileSystem;
use SPC\util\SPCTarget;

trait postgresql
{
    public function patchBeforeBuild(): bool
    {
        if (SPCTarget::getLibcVersion() === '2.17' && GNU_ARCH === 'aarch64') {
            FileSystem::replaceFileStr(
                $this->source_dir . '/src/port/pg_popcount_aarch64.c',
                'value & HWCAP_SVE',
                'value & 0',
            );
            FileSystem::replaceFileStr(
                $this->source_dir . '/src/port/pg_crc32c_armv8_choose.c',
                '#if defined(__linux__) && !defined(__aarch64__) && !defined(HWCAP2_CRC32)',
                '#if defined(__linux__) && !defined(HWCAP_CRC32)',
            );
            return true;
        }
        return false;
    }

    protected function build(): void
    {
        $builddir = BUILD_ROOT_PATH;
        $envs = '';
        $packages = 'zlib openssl readline libxml-2.0';
        $optional_packages = [
            'zstd' => 'libzstd',
            'ldap' => 'ldap',
            'libxslt' => 'libxslt',
            'icu' => 'icu-i18n',
        ];
        $error_exec_cnt = 0;

        foreach ($optional_packages as $lib => $pkg) {
            if ($this->getBuilder()->getLib($lib)) {
                $packages .= ' ' . $pkg;
                $output = shell()->execWithResult("pkg-config --static {$pkg}");
                $error_exec_cnt += $output[0] === 0 ? 0 : 1;
                logger()->info(var_export($output[1], true));
            }
        }

        $output = shell()->execWithResult("pkg-config --cflags-only-I --static {$packages}");
        $error_exec_cnt += $output[0] === 0 ? 0 : 1;
        $macos_15_bug_cflags = PHP_OS_FAMILY === 'Darwin' ? ' -Wno-unguarded-availability-new' : '';
        $cflags = '';
        if (!empty($output[1][0])) {
            $cflags = $output[1][0];
            $envs .= ' CPPFLAGS="-DPIC"';
            $cflags = "{$cflags} -fno-ident{$macos_15_bug_cflags}";
        }
        $output = shell()->execWithResult("pkg-config --libs-only-L --static {$packages}");
        $error_exec_cnt += $output[0] === 0 ? 0 : 1;
        if (!empty($output[1][0])) {
            $ldflags = $output[1][0];
            $envs .= " LDFLAGS=\"{$ldflags}\" ";
        }
        $output = shell()->execWithResult("pkg-config --libs-only-l --static {$packages}");
        $error_exec_cnt += $output[0] === 0 ? 0 : 1;
        if (!empty($output[1][0])) {
            $libs = $output[1][0];
            $libcpp = '';
            if ($this->builder->getLib('icu')) {
                $libcpp = $this instanceof LinuxLibraryBase ? ' -lstdc++' : ' -lc++';
            }
            $envs .= " LIBS=\"{$libs}{$libcpp}\" ";
        }
        if ($error_exec_cnt > 0) {
            throw new BuildFailureException('Failed to get pkg-config information!');
        }

        FileSystem::resetDir($this->source_dir . '/build');

        # 有静态链接配置  参考文件： src/interfaces/libpq/Makefile
        shell()->cd($this->source_dir . '/build')
            ->exec('sed -i.backup "s/invokes exit\'; exit 1;/invokes exit\';/"  ../src/interfaces/libpq/Makefile')
            ->exec('sed -i.backup "278 s/^/# /"  ../src/Makefile.shlib')
            ->exec('sed -i.backup "402 s/^/# /"  ../src/Makefile.shlib');

        // php source relies on the non-private encoding functions in libpgcommon.a
        FileSystem::replaceFileStr(
            $this->source_dir . '/src/common/Makefile',
            '$(OBJS_FRONTEND): CPPFLAGS += -DUSE_PRIVATE_ENCODING_FUNCS',
            '$(OBJS_FRONTEND): CPPFLAGS += -UUSE_PRIVATE_ENCODING_FUNCS -DFRONTEND',
        );

        // configure
        shell()->cd($this->source_dir . '/build')->initializeEnv($this)
            ->appendEnv(['CFLAGS' => $cflags])
            ->exec(
                "{$envs} ../configure " .
                "--prefix={$builddir} " .
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
            )
            ->exec($envs . ' make -C src/bin/pg_config install')
            ->exec($envs . ' make -C src/include install')
            ->exec($envs . ' make -C src/common install')
            ->exec($envs . ' make -C src/port install')
            ->exec($envs . ' make -C src/interfaces/libpq install');

        // remove dynamic libs
        shell()->cd($this->source_dir . '/build')
            ->exec("rm -rf {$builddir}/lib/*.so.*")
            ->exec("rm -rf {$builddir}/lib/*.so")
            ->exec("rm -rf {$builddir}/lib/*.dylib");

        FileSystem::replaceFileStr(BUILD_LIB_PATH . '/pkgconfig/libpq.pc', '-lldap', '-lldap -llber');
    }
}
