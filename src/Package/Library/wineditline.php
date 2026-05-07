<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('wineditline')]
class wineditline
{
    #[BuildFor('Windows')]
    public function build(LibraryPackage $lib): void
    {
        WindowsCMakeExecutor::create($lib)->build();
        FileSystem::copy($lib->getSourceDir() . '\lib64\edit_a.lib', $lib->getLibDir() . '\edit_a.lib');
        FileSystem::copyDir($lib->getSourceDir() . '\include\editline', $lib->getIncludeDir() . '\editline');
    }
}
