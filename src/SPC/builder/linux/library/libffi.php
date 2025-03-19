<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

class libffi extends LinuxLibraryBase
{
    public const NAME = 'libffi';

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function build(): void
    {
        [$lib, , $destdir] = SEPARATED_PATH;
        $arch = getenv('SPC_ARCH');

        shell()->cd($this->source_dir)
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->execWithEnv(
                './configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                "--host={$arch}-unknown-linux " .
                "--target={$arch}-unknown-linux " .
                '--prefix= ' .
                "--libdir={$lib}"
            )
            ->execWithEnv('make clean')
            ->execWithEnv("make -j{$this->builder->concurrency}")
            ->execWithEnv("make install DESTDIR={$destdir}");

        if (is_file(BUILD_ROOT_PATH . '/lib64/libffi.a')) {
            copy(BUILD_ROOT_PATH . '/lib64/libffi.a', BUILD_ROOT_PATH . '/lib/libffi.a');
            unlink(BUILD_ROOT_PATH . '/lib64/libffi.a');
        }
        $this->patchPkgconfPrefix(['libffi.pc']);
    }
}
