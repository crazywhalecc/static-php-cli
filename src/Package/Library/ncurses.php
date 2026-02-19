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
class ncurses
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function build(LibraryPackage $package, ToolchainInterface $toolchain): void
    {
        $dirdiff = new DirDiff(BUILD_BIN_PATH);

        UnixAutoconfExecutor::create($package)
            ->appendEnv([
                'LDFLAGS' => $toolchain->isStatic() ? '-static' : '',
            ])
            ->configure(
                '--enable-overwrite',
                '--with-curses-h',
                '--enable-pc-files',
                '--enable-echo',
                '--disable-widec',
                '--with-normal',
                '--with-ticlib',
                '--without-tests',
                '--without-dlsym',
                '--without-debug',
                '--enable-symlinks',
                "--bindir={$package->getBinDir()}",
                "--includedir={$package->getIncludeDir()}",
                "--libdir={$package->getLibDir()}",
                "--prefix={$package->getBuildRootPath()}",
            )
            ->make();
        $new_files = $dirdiff->getIncrementFiles(true);
        foreach ($new_files as $file) {
            @unlink(BUILD_BIN_PATH . '/' . $file);
        }

        shell()->cd(BUILD_ROOT_PATH)->exec('rm -rf share/terminfo');
        shell()->cd(BUILD_ROOT_PATH)->exec('rm -rf lib/terminfo');

        $pkgconf_list = ['form.pc', 'menu.pc', 'ncurses++.pc', 'ncurses.pc', 'panel.pc', 'tic.pc'];
        $package->patchPkgconfPrefix($pkgconf_list);

        foreach ($pkgconf_list as $pkgconf) {
            FileSystem::replaceFileStr("{$package->getLibDir()}/pkgconfig/{$pkgconf}", "-L{$package->getLibDir()}", '-L${libdir}');
        }
    }
}
