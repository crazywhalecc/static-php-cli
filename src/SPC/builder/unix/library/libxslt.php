<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;

trait libxslt
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function build(): void
    {
        $required_libs = '';
        foreach ($this->getDependencies() as $dep) {
            if ($dep instanceof LinuxLibraryBase) {
                $required_libs .= ' ' . $dep->getStaticLibFiles();
            }
        }
        shell()->cd($this->source_dir)
            ->exec(
                'CFLAGS="-I' . BUILD_INCLUDE_PATH . '" ' .
                "{$this->builder->getOption('library_path')} " .
                "{$this->builder->getOption('ld_library_path')} " .
                'LDFLAGS="-L' . BUILD_LIB_PATH . '" ' .
                "LIBS='{$required_libs} -lstdc++' " .
                './configure ' .
                '--enable-static --disable-shared ' .
                '--without-python ' .
                '--without-mem-debug ' .
                '--without-crypto ' .
                '--without-debug ' .
                '--without-debugger ' .
                '--with-libxml-prefix=' . escapeshellarg(BUILD_ROOT_PATH) . ' ' .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . escapeshellarg(BUILD_ROOT_PATH));
        $this->patchPkgconfPrefix(['libexslt.pc']);
    }
}
