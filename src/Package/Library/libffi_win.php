<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\EnvironmentException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\System\WindowsUtil;

#[Library('libffi-win')]
class libffi_win
{
    #[Validate]
    public function validate(): void
    {
        $ver = WindowsUtil::findVisualStudio();
        $vs_ver_dir = match ($ver['major_version']) {
            '17' => '\win32\vs17_x64',
            '16' => '\win32\vs16_x64',
            default => throw new EnvironmentException("Current VS version {$ver['major_version']} is not supported!"),
        };
        ApplicationContext::set('libffi_win_vs_ver_dir', $vs_ver_dir);
    }

    #[BuildFor('Windows')]
    public function build(LibraryPackage $lib): void
    {
        $vs_ver_dir = ApplicationContext::get('libffi_win_vs_ver_dir');
        cmd()->cd("{$lib->getSourceDir()}{$vs_ver_dir}")
            ->exec('msbuild libffi-msvc.sln /t:Rebuild /p:Configuration=Release /p:Platform=x64');
        FileSystem::createDir($lib->getLibDir());
        FileSystem::createDir($lib->getIncludeDir());

        FileSystem::copy("{$lib->getSourceDir()}{$vs_ver_dir}\\x64\\Release\\libffi.lib", "{$lib->getLibDir()}\\libffi.lib");
        FileSystem::copy("{$lib->getSourceDir()}{$vs_ver_dir}\\x64\\Release\\libffi.pdb", "{$lib->getLibDir()}\\libffi.pdb");
        FileSystem::copy("{$lib->getSourceDir()}\\include\\ffi.h", "{$lib->getIncludeDir()}\\ffi.h");
        FileSystem::replaceFileStr("{$lib->getIncludeDir()}\\ffi.h", '#define LIBFFI_H', "#define LIBFFI_H\n#define FFI_BUILDING");
        FileSystem::copy("{$lib->getSourceDir()}\\src\\x86\\ffitarget.h", "{$lib->getIncludeDir()}\\ffitarget.h");
        FileSystem::copy("{$lib->getSourceDir()}\\fficonfig.h", "{$lib->getIncludeDir()}\\fficonfig.h");
    }
}
