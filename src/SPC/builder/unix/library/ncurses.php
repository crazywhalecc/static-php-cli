<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixAutoconfExecutor;
use SPC\util\SPCTarget;

trait ncurses
{
    protected function build(): void
    {
        $filelist = FileSystem::scanDirFiles(BUILD_BIN_PATH, relative: true);

        UnixAutoconfExecutor::create($this)
            ->appendEnv([
                'LDFLAGS' => SPCTarget::isStatic() ? '-static' : '',
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
                '-enable-symlinks',
                "--bindir={$this->getBinDir()}",
                "--includedir={$this->getIncludeDir()}",
                "--libdir={$this->getLibDir()}",
                "--prefix={$this->getBuildRootPath()}",
            )
            ->make();
        $final = FileSystem::scanDirFiles(BUILD_BIN_PATH, relative: true);
        // Remove the new files
        $new_files = array_diff($final, $filelist);
        foreach ($new_files as $file) {
            @unlink(BUILD_BIN_PATH . '/' . $file);
        }

        shell()->cd(BUILD_ROOT_PATH)->exec('rm -rf share/terminfo');
        shell()->cd(BUILD_ROOT_PATH)->exec('rm -rf lib/terminfo');

        $pkgconf_list = ['form.pc', 'menu.pc', 'ncurses++.pc', 'ncurses.pc', 'panel.pc', 'tic.pc'];
        $this->patchPkgconfPrefix($pkgconf_list);

        foreach ($pkgconf_list as $pkgconf) {
            FileSystem::replaceFileStr(BUILD_LIB_PATH . '/pkgconfig/' . $pkgconf, '-L' . BUILD_LIB_PATH, '-L${libdir}');
        }
    }
}
