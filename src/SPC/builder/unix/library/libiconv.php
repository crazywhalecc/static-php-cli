<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait libiconv
{
    protected function build(): void
    {
        [,,$destdir] = SEPARATED_PATH;

        shell()->cd($this->source_dir)
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->execWithEnv(
                './configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                '--enable-extra-encodings ' .
                '--prefix='
            )
            ->execWithEnv('make clean')
            ->execWithEnv("make -j{$this->builder->concurrency}")
            ->execWithEnv('make install DESTDIR=' . $destdir);

        if (file_exists(BUILD_BIN_PATH . '/iconv')) {
            unlink(BUILD_BIN_PATH . '/iconv');
        }
    }
}
