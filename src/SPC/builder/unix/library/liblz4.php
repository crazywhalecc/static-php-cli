<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait liblz4
{
    public function patchBeforeBuild(): bool
    {
        // disable executables
        FileSystem::replaceFileStr($this->source_dir . '/programs/Makefile', 'install: lz4', "install: lz4\n\ninstallewfwef: lz4");
        return true;
    }

    protected function build(): void
    {
        shell()->cd($this->source_dir)->initializeEnv($this)
            ->exec("make PREFIX='' clean")
            ->exec("make lib -j{$this->builder->concurrency} PREFIX=''");

        FileSystem::replaceFileStr($this->source_dir . '/Makefile', '$(MAKE) -C $(PRGDIR) $@', '');

        shell()->cd($this->source_dir)
            ->exec("make install PREFIX='' DESTDIR=" . BUILD_ROOT_PATH);

        $this->patchPkgconfPrefix(['liblz4.pc']);
    }
}
