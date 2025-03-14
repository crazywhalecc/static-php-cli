<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait libacl
{
    /**
     * @throws FileSystemException
     */
    public function patchBeforeMake(): bool
    {
        $file_path = SOURCE_PATH . '/php-src/Makefile';
        $file_content = FileSystem::readFile($file_path);
        if (!preg_match('/FPM_EXTRA_LIBS =(.*)-lacl/', $file_content)) {
            return false;
        }
        FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/Makefile', '/FPM_EXTRA_LIBS =(.*)-lacl ?(.*)/', 'FPM_EXTRA_LIBS =$1$2');
        return true;
    }

    /**
     * @throws RuntimeException
     */
    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->setEnv([
                'CFLAGS' => trim('-I' . BUILD_INCLUDE_PATH . ' ' . $this->getLibExtraCFlags()),
                'LDFLAGS' => trim('-L' . BUILD_LIB_PATH . ' ' . $this->getLibExtraLdFlags()),
                'LIBS' => $this->getLibExtraLibs(),
            ])
            ->execWithEnv('libtoolize --force --copy')
            ->execWithEnv('./autogen.sh')
            ->execWithEnv('./configure --prefix= --enable-static --disable-shared --disable-tests --disable-nls')
            ->execWithEnv("make -j {$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

        $this->patchPkgconfPrefix(['libacl.pc'], PKGCONF_PATCH_PREFIX);
    }
}
