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
        // zig-cc / clang -flto -c with multiple input files only produces an .o for the first source,
        // leaving liblz4.a with just lz4.o. Compile sources individually so all .o files exist.
        FileSystem::replaceFileStr(
            $this->source_dir . '/lib/Makefile',
            "liblz4.a: \$(SRCFILES)\nifeq (\$(BUILD_STATIC),yes)  # can be disabled on command line\n\t@echo compiling static library\n\t\$(COMPILE.c) \$^\n\t\$(AR) rcs \$@ *.o\nendif",
            "liblz4.a: \$(SRCFILES:.c=.o)\nifeq (\$(BUILD_STATIC),yes)  # can be disabled on command line\n\t@echo compiling static library\n\t\$(AR) rcs \$@ \$^\nendif"
        );
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

        foreach (FileSystem::scanDirFiles(BUILD_LIB_PATH . '/', false, true) as $filename) {
            if (str_starts_with($filename, 'liblz4') && (str_contains($filename, '.so') || str_ends_with($filename, '.dylib'))) {
                unlink(BUILD_ROOT_PATH . '/lib/' . $filename);
            }
        }
    }
}
