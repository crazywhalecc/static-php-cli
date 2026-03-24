<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Exception\BuildFailureException;
use StaticPHP\Exception\EnvironmentException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\System\WindowsUtil;

#[Library('libsodium')]
class libsodium
{
    #[PatchBeforeBuild]
    #[PatchDescription('Replace SODIUM_STATIC define guard with unconditional #if 1 for MSVC static linking')]
    public function patchBeforeBuild(LibraryPackage $lib): void
    {
        spc_skip_if(SystemTarget::getTargetOS() !== 'Windows', 'This patch is only for Windows builds.');
        FileSystem::replaceFileStr($lib->getSourceDir() . '\src\libsodium\include\sodium\export.h', '#ifdef SODIUM_STATIC', '#if 1');
    }

    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)->configure()->make();

        // Patch pkg-config file
        $lib->patchPkgconfPrefix(['libsodium.pc'], PKGCONF_PATCH_PREFIX);
    }

    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        $ver = WindowsUtil::findVisualStudio();
        $vs_ver_dir = match ($ver['major_version']) {
            '17' => '\vs2022',
            '16' => '\vs2019',
            default => throw new EnvironmentException("Current VS version {$ver['major_version']} is not supported yet!"),
        };

        cmd()->cd("{$lib->getSourceDir()}\\builds\\msvc{$vs_ver_dir}")
            ->exec('msbuild libsodium.sln /t:Rebuild /p:Configuration=StaticRelease /p:Platform=x64 /p:PreprocessorDefinitions="SODIUM_STATIC=1"');
        FileSystem::createDir($lib->getLibDir());
        FileSystem::createDir($lib->getIncludeDir());

        // copy include
        FileSystem::copyDir("{$lib->getSourceDir()}\\src\\libsodium\\include\\sodium", "{$lib->getIncludeDir()}\\sodium");
        FileSystem::copy("{$lib->getSourceDir()}\\src\\libsodium\\include\\sodium.h", "{$lib->getIncludeDir()}\\sodium.h");
        // copy lib
        $ls = FileSystem::scanDirFiles("{$lib->getSourceDir()}\\bin");
        $find = false;
        foreach ($ls as $file) {
            if (str_ends_with($file, 'libsodium.lib')) {
                FileSystem::copy($file, "{$lib->getLibDir()}\\libsodium.lib");
                $find = true;
            }
            if (str_ends_with($file, 'libsodium.pdb')) {
                FileSystem::copy($file, "{$lib->getLibDir()}\\libsodium.pdb");
            }
        }
        if (!$find) {
            throw new BuildFailureException("Build libsodium success, but cannot find libsodium.lib in {$lib->getSourceDir()}\\bin .");
        }
    }
}
