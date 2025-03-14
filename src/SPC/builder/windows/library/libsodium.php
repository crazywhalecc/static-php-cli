<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\builder\windows\SystemUtil;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

class libsodium extends WindowsLibraryBase
{
    public const NAME = 'libsodium';

    protected function build()
    {
        FileSystem::replaceFileStr($this->source_dir . '\src\libsodium\include\sodium\export.h', '#ifdef SODIUM_STATIC', '#if 1');
        $vs_ver_dir = match (SystemUtil::findVisualStudio()['version']) {
            'vs17' => '\vs2022',
            'vs16' => '\vs2019',
            default => throw new RuntimeException('Current VS version is not supported yet!'),
        };

        // start build
        cmd()->cd($this->source_dir . '\builds\msvc\\' . $vs_ver_dir)
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('msbuild'),
                'libsodium.sln /t:Rebuild /p:Configuration=StaticRelease /p:Platform=x64 /p:PreprocessorDefinitions="SODIUM_STATIC=1"'
            );
        FileSystem::createDir(BUILD_LIB_PATH);
        FileSystem::createDir(BUILD_INCLUDE_PATH);

        // copy include
        FileSystem::copyDir($this->source_dir . '\src\libsodium\include\sodium', BUILD_INCLUDE_PATH . '\sodium');
        copy($this->source_dir . '\src\libsodium\include\sodium.h', BUILD_INCLUDE_PATH . '\sodium.h');
        // copy lib
        $ls = FileSystem::scanDirFiles($this->source_dir . '\bin');
        $find = false;
        foreach ($ls as $file) {
            if (str_ends_with($file, 'libsodium.lib')) {
                copy($file, BUILD_LIB_PATH . '\libsodium.lib');
                $find = true;
            }
            if (str_ends_with($file, 'libsodium.pdb')) {
                copy($file, BUILD_LIB_PATH . '\libsodium.pdb');
            }
        }
        if (!$find) {
            throw new RuntimeException('libsodium.lib not found');
        }
    }
}
