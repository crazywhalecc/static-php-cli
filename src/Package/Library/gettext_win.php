<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\EnvironmentException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\System\WindowsUtil;

#[Library('gettext-win')]
class gettext_win
{
    #[Validate]
    public function validate(): void
    {
        $ver = WindowsUtil::findVisualStudio();
        $vs_ver_dir = match ($ver['major_version']) {
            '17' => '\MSVC17',
            '16' => '\MSVC16',
            default => throw new EnvironmentException("Current VS version {$ver['major_version']} is not supported yet!"),
        };
        ApplicationContext::set('gettext_win_vs_ver_dir', $vs_ver_dir);
    }

    #[PatchBeforeBuild]
    public function patchBeforeBuild(LibraryPackage $lib): void
    {
        $vs_ver_dir = ApplicationContext::get('gettext_win_vs_ver_dir');
        $vcxproj = "{$lib->getSourceDir()}{$vs_ver_dir}\\libintl_static\\libintl_static.vcxproj";
        // libintl_static uses /MD (MultiThreadedDLL) in Release configs, which causes unresolved __imp_* symbols
        // when linking into PHP statically. Patch to /MT (MultiThreaded) for static CRT compatibility.
        FileSystem::replaceFileStr($vcxproj, '<RuntimeLibrary>MultiThreadedDLL</RuntimeLibrary>', '<RuntimeLibrary>MultiThreaded</RuntimeLibrary>');
    }

    #[BuildFor('Windows')]
    public function build(LibraryPackage $lib): void
    {
        $vs_ver_dir = ApplicationContext::get('gettext_win_vs_ver_dir');
        cmd()->cd("{$lib->getSourceDir()}{$vs_ver_dir}\\libintl_static")
            ->exec('msbuild libintl_static.vcxproj /t:Rebuild /p:Configuration=Release /p:Platform=x64 /p:WindowsTargetPlatformVersion=10.0');
        FileSystem::createDir($lib->getLibDir());
        FileSystem::createDir($lib->getIncludeDir());
        // libintl_a.lib is the static library output; copy as libintl.lib for linker compatibility
        FileSystem::copy("{$lib->getSourceDir()}{$vs_ver_dir}\\libintl_static\\x64\\Release\\libintl_a.lib", "{$lib->getLibDir()}\\libintl_a.lib");
        // libgnuintl.h is the public API header, installed as libintl.h
        FileSystem::copy("{$lib->getSourceDir()}\\source\\gettext-runtime\\intl\\libgnuintl.h", "{$lib->getIncludeDir()}\\libintl.h");
    }
}
