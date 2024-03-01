<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait libuuid
{
    public function patchBeforeBuild(): bool
    {
        FileSystem::replaceFileStr($this->source_dir . '/configure', '-${am__api_version}', '');
        return true;
    }

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->exec('chmod +x configure')
            ->exec('chmod +x install-sh')
            ->exec(
                './configure ' .
                '--enable-static --disable-shared ' .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['uuid.pc']);
    }
}
