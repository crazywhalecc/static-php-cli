<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;

trait ast
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->exec('phpize')
            ->execWithEnv(
                './configure '
            )
            ->execWithEnv('make clean')
            ->execWithEnv('make')
            ->execWithEnv('make install DESTDIR=' . BUILD_ROOT_PATH);

        $this->cleanLaFiles();
    }
}
