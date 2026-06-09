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
                'x86_64' => is_dir('/usr/local/etc') ? '/usr/local/etc' : '/opt/local/etc',
                'aarch64' => is_dir('/opt/homebrew/etc') ? '/opt/homebrew/etc' : '/opt/local/etc',
                default => throw new WrongUsageException('Unsupported architecture: ' . GNU_ARCH),
            },
            'Linux' => '/etc',
            default => throw new WrongUsageException("Unsupported OS: {$os}"),
        };

        // unixodbc bundles libltdl; libltdl is incompatible with -flto
        // (https://bugs.gentoo.org/532672).
        $stripLto = static fn (string $s): string => clean_spaces((string) preg_replace('/(^|\s)-flto(=\S+)?(?=\s|$)/', ' ', $s));
        $cflags = $stripLto((string) getenv('SPC_DEFAULT_CFLAGS'));
        $cxxflags = $stripLto((string) getenv('SPC_DEFAULT_CXXFLAGS'));
        $ldflags = $stripLto((string) getenv('SPC_DEFAULT_LDFLAGS'));

        $make = UnixAutoconfExecutor::create($this)
            ->setEnv([
                'CFLAGS' => $cflags,
                'CXXFLAGS' => $cxxflags,
                'LDFLAGS' => $ldflags,
            ])
            ->configure(
                '--disable-debug',
                '--disable-dependency-tracking',
                "--with-libiconv-prefix={$this->getBuildRootPath()}",
                '--with-included-ltdl',
                "--sysconfdir={$sysconf_selector}",
                '--enable-gui=no',
            );

        // The exe/ subdirectory builds odbcinst/iusql/etc, turn it into a no-op
        file_put_contents(
            "{$this->getSourceDir()}/exe/Makefile",
            ".PHONY: all install clean check distclean install-strip\nall install clean check distclean install-strip:\n\t@true\n",
        );

        $make->make();
        $this->patchPkgconfPrefix(['odbc.pc', 'odbccr.pc', 'odbcinst.pc']);
        $this->patchLaDependencyPrefix();
    }
}
