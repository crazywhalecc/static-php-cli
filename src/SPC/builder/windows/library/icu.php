<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class icu extends WindowsLibraryBase
{
    public const NAME = 'icu';

    protected function build()
    {
        // start build
        cmd()->cd($this->source_dir)
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('msbuild'),
                'source\allinone\allinone.sln /p:Configuration=Release /p:Platform=x64 /t:io /p:SkipUWP=true'
            );
        FileSystem::createDir(BUILD_LIB_PATH);
        FileSystem::createDir(BUILD_INCLUDE_PATH);
        copy($this->source_dir . '\lib64\icuuc.lib', BUILD_LIB_PATH . '\icuuc.lib');
        copy($this->source_dir . '\lib64\icudt.lib', BUILD_LIB_PATH . '\icudt.lib');
        copy($this->source_dir . '\lib64\icuin.lib', BUILD_LIB_PATH . '\icuin.lib');
        copy($this->source_dir . '\lib64\icuio.lib', BUILD_LIB_PATH . '\icuio.lib');
        FileSystem::copyDir($this->source_dir . '\include\unicode', BUILD_INCLUDE_PATH . '\unicode');
    }
}
