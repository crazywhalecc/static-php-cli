<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;

class libargon2 extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libargon2;

    public const NAME = 'libargon2';

    public function patchBeforeBuild(): bool
    {
        // detect libsodium (The libargon2 conflicts with the libsodium library.)
        if ($this->builder->getLib('libsodium') !== null) {
            throw new WrongUsageException('libargon2 (required by password-argon2) conflicts with the libsodium library !');
        }
        FileSystem::replaceFileStr($this->source_dir . '/Makefile', 'LIBRARY_REL ?= lib/x86_64-linux-gnu', 'LIBRARY_REL ?= lib');
        return true;
    }
}
