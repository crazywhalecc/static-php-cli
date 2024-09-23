<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\store\FileSystem;

class icu extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\icu;

    public const NAME = 'icu';

    protected function build(): void
    {
        $root = BUILD_ROOT_PATH;
        shell()->cd($this->source_dir . '/source')
            ->exec("./runConfigureICU MacOSX --enable-static --disable-shared --disable-extras --disable-samples --disable-tests --prefix={$root}")
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install');

        $this->patchPkgconfPrefix(['icu-i18n.pc', 'icu-io.pc', 'icu-uc.pc'], PKGCONF_PATCH_PREFIX);
        FileSystem::removeDir(BUILD_LIB_PATH . '/icu');
    }
}
