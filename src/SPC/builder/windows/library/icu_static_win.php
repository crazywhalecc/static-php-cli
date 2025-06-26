<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class icu_static_win extends WindowsLibraryBase
{
    public const NAME = 'icu-static-win';

    protected function build(): void
    {
        copy("{$this->source_dir}\\x64-windows-static\\lib\\icudt.lib", "{$this->getLibDir()}\\icudt.lib");
        copy("{$this->source_dir}\\x64-windows-static\\lib\\icuin.lib", "{$this->getLibDir()}\\icuin.lib");
        copy("{$this->source_dir}\\x64-windows-static\\lib\\icuio.lib", "{$this->getLibDir()}\\icuio.lib");
        copy("{$this->source_dir}\\x64-windows-static\\lib\\icuuc.lib", "{$this->getLibDir()}\\icuuc.lib");

        // create libpq folder in buildroot/includes/libpq
        if (!file_exists("{$this->getIncludeDir()}\\unicode")) {
            mkdir("{$this->getIncludeDir()}\\unicode");
        }

        FileSystem::copyDir("{$this->source_dir}\\x64-windows-static\\include\\unicode", "{$this->getIncludeDir()}\\unicode");
    }
}
