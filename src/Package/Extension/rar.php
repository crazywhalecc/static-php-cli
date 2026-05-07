<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Extension('rar')]
class rar extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-rar')]
    #[PatchDescription('rar extension workaround for newer Xcode clang (>= 15.0)')]
    public function patchBeforeBuildconf(): void
    {
        // workaround for newer Xcode clang (>= 15.0)
        if (SystemTarget::getTargetOS() === 'Darwin') {
            FileSystem::replaceFileStr("{$this->getSourceDir()}/config.m4", '-Wall -fvisibility=hidden', '-Wall -Wno-incompatible-function-pointer-types -fvisibility=hidden');
        }
    }
}
