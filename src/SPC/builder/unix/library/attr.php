<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\RuntimeException;

trait attr
{
    /**
     * @throws RuntimeException
     */
    protected function build(): void
    {
        shell()->cd($this->source_dir)->initLibBuildEnv($this)
            ->appendEnv(['CFLAGS' => "-I{$this->getIncludeDir()}", 'LDFLAGS' => "-L{$this->getLibDir()}"])
            ->exec('libtoolize --force --copy')
            ->exec('./autogen.sh || autoreconf -if')
            ->exec('./configure --prefix= --enable-static --disable-shared --with-pic --disable-nls')
            ->exec("make -j {$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

        $this->patchPkgconfPrefix(['libattr.pc'], PKGCONF_PATCH_PREFIX);
    }
}
