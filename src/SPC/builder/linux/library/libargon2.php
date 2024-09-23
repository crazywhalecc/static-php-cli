<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\store\FileSystem;

class libargon2 extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libargon2;

    public const NAME = 'libargon2';

    public function patchBeforeBuild(): bool
    {
        FileSystem::replaceFileStr($this->source_dir . '/Makefile', 'LIBRARY_REL ?= lib/x86_64-linux-gnu', 'LIBRARY_REL ?= lib');
        return true;
    }
}
