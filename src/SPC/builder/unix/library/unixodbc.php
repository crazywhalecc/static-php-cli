<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\WrongUsageException;
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
        UnixAutoconfExecutor::create($this)
            ->configure(
                '--disable-debug',
                '--disable-dependency-tracking',
                "--with-libiconv-prefix={$this->getBuildRootPath()}",
                '--with-included-ltdl',
                "--sysconfdir={$sysconf_selector}",
                '--enable-gui=no',
            )
            ->make();
        $this->patchPkgconfPrefix(['odbc.pc', 'odbccr.pc', 'odbcinst.pc']);
        $this->patchLaDependencyPrefix();
    }
}
