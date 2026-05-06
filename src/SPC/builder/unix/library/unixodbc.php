<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
use SPC\util\executor\UnixAutoconfExecutor;

trait unixodbc
{
    protected function build(): void
    {
        $sysconf_selector = match (PHP_OS_FAMILY) {
            'Darwin' => match (GNU_ARCH) {
                'x86_64' => '/usr/local/etc',
                'aarch64' => '/opt/homebrew/etc',
                default => throw new WrongUsageException('Unsupported architecture: ' . GNU_ARCH),
            },
            'Linux' => '/etc',
            default => throw new WrongUsageException('Unsupported OS: ' . PHP_OS_FAMILY),
        };
        // libltdl is incompatible with -flto (https://bugs.gentoo.org/532672)
        $stripLto = static fn ($s) => preg_replace('/(^|\s)-flto(=\S+)?(?=\s|$)/', ' ', (string) $s);
        $origC = $this->builder->arch_c_flags;
        $origCxx = $this->builder->arch_cxx_flags;
        $origLd = $this->builder->arch_ld_flags;
        $this->builder->arch_c_flags = clean_spaces($stripLto($origC));
        $this->builder->arch_cxx_flags = clean_spaces($stripLto($origCxx));
        $this->builder->arch_ld_flags = clean_spaces($stripLto($origLd));
        $make = UnixAutoconfExecutor::create($this)
            ->configure(
                '--disable-debug',
                '--disable-dependency-tracking',
                "--with-libiconv-prefix={$this->getBuildRootPath()}",
                '--with-included-ltdl',
                "--sysconfdir={$sysconf_selector}",
                '--enable-gui=no',
            );

        file_put_contents(
            "{$this->source_dir}/exe/Makefile",
            ".PHONY: all install clean check distclean install-strip\nall install clean check distclean install-strip:\n\t@true\n",
        );

        $make->make();
        $this->builder->arch_c_flags = $origC;
        $this->builder->arch_cxx_flags = $origCxx;
        $this->builder->arch_ld_flags = $origLd;

        $pkgConfigs = ['odbc.pc', 'odbccr.pc', 'odbcinst.pc'];
        $this->patchPkgconfPrefix($pkgConfigs);
        foreach ($pkgConfigs as $file) {
            FileSystem::replaceFileStr(
                BUILD_LIB_PATH . "/pkgconfig/{$file}",
                '$(top_build_prefix)libltdl/libltdlc.la',
                ''
            );
        }
        $this->patchLaDependencyPrefix();
    }
}
