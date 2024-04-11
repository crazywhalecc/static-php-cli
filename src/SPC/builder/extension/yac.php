<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('yac')]
class yac extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/yac/storage/allocator/yac_allocator.h', 'defined(HAVE_SHM_MMAP_ANON)', 'defined(YAC_ALLOCATOR_H)');
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/yac/serializer/igbinary.c', '#ifdef YAC_ENABLE_IGBINARY', '#if 1');
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/yac/serializer/json.c', '#if YAC_ENABLE_JSON', '#if 1');
        return true;
    }

    public function getUnixConfigureArg(): string
    {
        return '--enable-yac --enable-igbinary --enable-json';
    }
}
