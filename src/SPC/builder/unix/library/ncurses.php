<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait ncurses
{
    protected function build(): void
    {
        $filelist = FileSystem::scanDirFiles(BUILD_BIN_PATH, relative: true);
        shell()->cd($this->source_dir)
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->execWithEnv(
                './configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                '--enable-overwrite ' .
                '--with-curses-h ' .
                '--enable-pc-files ' .
                '--enable-echo ' .
                '--disable-widec ' .
                '--with-normal ' .
                '--with-ticlib ' .
                '--without-tests ' .
                '--without-dlsym ' .
                '--without-debug ' .
                '-enable-symlinks ' .
                '--bindir=' . BUILD_ROOT_PATH . '/bin ' .
                '--includedir=' . BUILD_ROOT_PATH . '/include ' .
                '--libdir=' . BUILD_ROOT_PATH . '/lib ' .
                '--prefix=' . BUILD_ROOT_PATH
            )
            ->execWithEnv('make clean')
            ->execWithEnv("make -j{$this->builder->concurrency}")
            ->execWithEnv('make install');

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
