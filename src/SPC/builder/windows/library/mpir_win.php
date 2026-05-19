<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class mpir_win extends WindowsLibraryBase
{
    public const NAME = 'mpir-win';

    protected function build(): void
    {
        FileSystem::createDir(BUILD_LIB_PATH);
        FileSystem::createDir(BUILD_INCLUDE_PATH . '\mpir');

        copy($this->source_dir . '\lib\mpir_a.lib', BUILD_LIB_PATH . '\mpir_a.lib');

        foreach (['gmp.h', 'mpir.h', 'gmp-mparam.h', 'config.h'] as $header) {
            copy(
                $this->source_dir . '\include\mpir\\' . $header,
                BUILD_INCLUDE_PATH . '\mpir\\' . $header,
            );
        }
    }
}
