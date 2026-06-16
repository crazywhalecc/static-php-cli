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

#[Library('libiconv-win')]
class libiconv_win
{
    #[Validate]
    public function validate(): void
    {
        $ver = WindowsUtil::findVisualStudio();
        $vs_ver_dir = match ($ver['major_version']) {
            '18', // VS 2026 reuses the VS2022 (MSVC17) solution, which msbuild builds via forward compatibility.
            '17' => '\MSVC17',
            '16' => '\MSVC16',
            default => throw new EnvironmentException("Current VS version {$ver['major_version']} is not supported yet!"),
        };
        ApplicationContext::set('vs_ver_dir', $vs_ver_dir);
    }

    #[BuildFor('Windows')]
    public function build(LibraryPackage $lib): void
    {
        $vs_ver_dir = ApplicationContext::get('vs_ver_dir');
        cmd()->cd("{$lib->getSourceDir()}{$vs_ver_dir}")
            // WholeProgramOptimization (/GL) emits LTCG objects that frankenphp's lld-link cannot
            // read ("is not a native COFF file"); disable it so the .lib stays plain COFF.
            ->exec('msbuild libiconv.sln /t:Rebuild /p:Configuration=Release /p:Platform=x64 /p:WholeProgramOptimization=false');
        FileSystem::createDir($lib->getLibDir());
        FileSystem::createDir($lib->getIncludeDir());
        FileSystem::copy("{$lib->getSourceDir()}{$vs_ver_dir}\\x64\\lib\\libiconv.lib", "{$lib->getLibDir()}\\libiconv.lib");
        FileSystem::copy("{$lib->getSourceDir()}{$vs_ver_dir}\\x64\\lib\\libiconv_a.lib", "{$lib->getLibDir()}\\libiconv_a.lib");
        FileSystem::copy("{$lib->getSourceDir()}\\source\\include\\iconv.h", "{$lib->getIncludeDir()}\\iconv.h");
    }
}
