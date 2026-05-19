<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class libintl_win extends WindowsLibraryBase
{
    public const NAME = 'libintl-win';

    protected function build(): void
    {
        FileSystem::createDir(BUILD_LIB_PATH);
        FileSystem::createDir(BUILD_INCLUDE_PATH);

        copy($this->source_dir . '\lib\libintl_a.lib', BUILD_LIB_PATH . '\libintl_a.lib');
        copy($this->source_dir . '\include\libintl.h', BUILD_INCLUDE_PATH . '\libintl.h');
    }
}
