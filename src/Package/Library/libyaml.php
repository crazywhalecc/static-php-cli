<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Library('libyaml')]
class libyaml
{
    #[PatchBeforeBuild]
    #[PatchDescription('Copy missing cmake helper files required for MSVC build (not included in libyaml git source)')]
    public function patchBeforeBuild(LibraryPackage $lib): void
    {
        spc_skip_if(SystemTarget::getTargetOS() !== 'Windows', 'This patch is only for Windows builds.');
        // check missing files: cmake\config.h.in and .\YamlConfig.cmake.in
        if (!file_exists($lib->getSourceDir() . '\cmake\config.h.in')) {
            FileSystem::createDir($lib->getSourceDir() . '\cmake');
            FileSystem::copy(ROOT_DIR . '/src/globals/extra/libyaml_config.h.in', $lib->getSourceDir() . '\cmake\config.h.in');
        }
        if (!file_exists($lib->getSourceDir() . '\YamlConfig.cmake.in')) {
            FileSystem::copy(ROOT_DIR . '/src/globals/extra/libyaml_yamlConfig.cmake.in', $lib->getSourceDir() . '\YamlConfig.cmake.in');
        }
    }

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)->configure()->make();
    }

    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        WindowsCMakeExecutor::create($lib)
            ->addConfigureArgs('-DBUILD_TESTING=OFF')
            ->build();
    }
}
