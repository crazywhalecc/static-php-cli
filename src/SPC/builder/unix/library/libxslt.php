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
        shell()->cd($this->source_dir)->initializeEnv($this)
            ->appendEnv([
                'CFLAGS' => "-I{$this->getIncludeDir()}",
                'LDFLAGS' => "-L{$this->getLibDir()}",
                'LIBS' => "{$required_libs} -lstdc++",
            ])
            ->exec(
                "{$this->builder->getOption('library_path')} " .
                "{$this->builder->getOption('ld_library_path')} " .
                './configure ' .
                '--enable-static --disable-shared ' .
                '--with-pic ' .
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
        $this->patchPkgconfPrefix(['libxslt.pc', 'libexslt.pc']);
        $this->patchLaDependencyPrefix();
        shell()->cd(BUILD_LIB_PATH)
            ->exec("ar -t libxslt.a | grep '\\.a$' | xargs -n1 ar d libxslt.a")
            ->exec("ar -t libexslt.a | grep '\\.a$' | xargs -n1 ar d libexslt.a");
    }
}
