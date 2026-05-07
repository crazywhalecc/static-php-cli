<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\SystemTarget;

#[Library('unixodbc')]
class unixodbc extends LibraryPackage
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(): void
    {
        $sysconf_selector = match ($os = SystemTarget::getTargetOS()) {
            'Darwin' => match (SystemTarget::getTargetArch()) {
                'x86_64' => '/usr/local/etc',
                'aarch64' => '/opt/homebrew/etc',
                default => throw new WrongUsageException('Unsupported architecture: ' . GNU_ARCH),
            },
            'Linux' => '/etc',
            default => throw new WrongUsageException("Unsupported OS: {$os}"),
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
