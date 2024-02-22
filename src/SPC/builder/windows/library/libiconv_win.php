<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\builder\windows\SystemUtil;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

class libiconv_win extends WindowsLibraryBase
{
    public const NAME = 'libiconv-win';

    protected function build()
    {
        $vs_ver_dir = match (SystemUtil::findVisualStudio()['version']) {
            'vs17' => '/MSVC17',
            'vs16' => '/MSVC16',
            default => throw new RuntimeException('Current VS version is not supported yet!'),
        };

        // start build
        cmd()->cd($this->source_dir . $vs_ver_dir)
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('msbuild'),
                'libiconv.sln /t:Rebuild /p:Configuration=Release /p:Platform=x64'
            );
        FileSystem::createDir(BUILD_LIB_PATH);
        FileSystem::createDir(BUILD_INCLUDE_PATH);
        copy($this->source_dir . $vs_ver_dir . '\x64\lib\libiconv.lib', BUILD_LIB_PATH . '\libiconv.lib');
        copy($this->source_dir . $vs_ver_dir . '\x64\lib\libiconv_a.lib', BUILD_LIB_PATH . '\libiconv_a.lib');
        copy($this->source_dir . '\source\include\iconv.h', BUILD_INCLUDE_PATH . '\iconv.h');
    }
}
