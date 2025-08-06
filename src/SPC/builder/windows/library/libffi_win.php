<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\builder\windows\SystemUtil;
use SPC\exception\EnvironmentException;
use SPC\store\FileSystem;

class libffi_win extends WindowsLibraryBase
{
    public const NAME = 'libffi-win';

    private string $vs_ver_dir;

    public function validate(): void
    {
        $this->vs_ver_dir = match ($ver = SystemUtil::findVisualStudio()['version']) {
            'vs17' => '\win32\vs17_x64',
            'vs16' => '\win32\vs16_x64',
            default => throw new EnvironmentException("Current VS version {$ver} is not supported !"),
        };
    }

    protected function build(): void
    {
        // start build
        cmd()->cd($this->source_dir . $this->vs_ver_dir)
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('msbuild'),
                'libffi-msvc.sln /t:Rebuild /p:Configuration=Release /p:Platform=x64'
            );
        FileSystem::createDir(BUILD_LIB_PATH);
        FileSystem::createDir(BUILD_INCLUDE_PATH);

        FileSystem::copy("{$this->source_dir}{$this->vs_ver_dir}\\x64\\Release\\libffi.lib", BUILD_LIB_PATH . '\libffi.lib');
        FileSystem::copy("{$this->source_dir}{$this->vs_ver_dir}\\x64\\Release\\libffi.pdb", BUILD_LIB_PATH . '\libffi.pdb');
        FileSystem::copy($this->source_dir . '\include\ffi.h', BUILD_INCLUDE_PATH . '\ffi.h');

        FileSystem::replaceFileStr(BUILD_INCLUDE_PATH . '\ffi.h', '#define LIBFFI_H', "#define LIBFFI_H\n#define FFI_BUILDING");
        FileSystem::copy($this->source_dir . '\src\x86\ffitarget.h', BUILD_INCLUDE_PATH . '\ffitarget.h');
        FileSystem::copy($this->source_dir . '\fficonfig.h', BUILD_INCLUDE_PATH . '\fficonfig.h');

        // copy($this->source_dir . '\msvc_build\out\static-Release\X64\libffi.lib', BUILD_LIB_PATH . '\libffi.lib');
        // copy($this->source_dir . '\msvc_build\include\ffi.h', BUILD_INCLUDE_PATH . '\ffi.h');
        // copy($this->source_dir . '\msvc_build\include\fficonfig.h', BUILD_INCLUDE_PATH . '\fficonfig.h');
        // copy($this->source_dir . '\src\x86\ffitarget.h', BUILD_INCLUDE_PATH . '\ffitarget.h');

        // FileSystem::replaceFileStr(BUILD_INCLUDE_PATH . '\ffi.h', '..\..\src\x86\ffitarget.h', 'ffitarget.h');
    }
}
