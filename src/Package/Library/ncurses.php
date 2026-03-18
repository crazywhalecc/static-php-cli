<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Util\DirDiff;
use StaticPHP\Util\FileSystem;

#[Library('ncurses')]
#[Library('ncursesw')]
class ncurses
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function build(LibraryPackage $package, ToolchainInterface $toolchain): void
    {
        $dirdiff = new DirDiff(BUILD_BIN_PATH);

        $ac = UnixAutoconfExecutor::create($package)
            ->appendEnv([
                'LDFLAGS' => $toolchain->isStatic() ? '-static' : '',
            ]);
        $wide = $package->getName() === 'ncurses' ? ['--disable-widec'] : [];
        // Include standard system terminfo paths as fallback so binaries linking this ncurses
        // (e.g. htop) can find terminfo on any target system without needing TERMINFO_DIRS set.
        $terminfo_dirs = implode(':', [
            "{$package->getBuildRootPath()}/share/terminfo",
            '/etc/terminfo',
            '/lib/terminfo',
            '/usr/share/terminfo',
        ]);
        $ac->configure(
            '--enable-overwrite',
            '--with-curses-h',
            '--enable-pc-files',
            '--enable-echo',
            '--with-normal',
            '--with-ticlib',
            '--without-tests',
            '--without-dlsym',
            '--without-debug',
            '--enable-symlinks',
            "--with-terminfo-dirs={$terminfo_dirs}",
            "--bindir={$package->getBinDir()}",
            "--includedir={$package->getIncludeDir()}",
            "--libdir={$package->getLibDir()}",
            "--prefix={$package->getBuildRootPath()}",
            ...$wide,
        )
            ->make();
        $new_files = $dirdiff->getIncrementFiles(true);
        foreach ($new_files as $file) {
            @unlink(BUILD_BIN_PATH . '/' . $file);
        }

        // shell()->cd(BUILD_ROOT_PATH)->exec('rm -rf share/terminfo');
        // shell()->cd(BUILD_ROOT_PATH)->exec('rm -rf lib/terminfo');

        $suffix = $package->getName() === 'ncursesw' ? 'w' : '';
        $pkgconf_list = ["form{$suffix}.pc", "menu{$suffix}.pc", "ncurses++{$suffix}.pc", "ncurses{$suffix}.pc", "panel{$suffix}.pc", "tic{$suffix}.pc"];
        $package->patchPkgconfPrefix($pkgconf_list);

        foreach ($pkgconf_list as $pkgconf) {
            FileSystem::replaceFileStr("{$package->getLibDir()}/pkgconfig/{$pkgconf}", "-L{$package->getLibDir()}", '-L${libdir}');
        }
    }
}
