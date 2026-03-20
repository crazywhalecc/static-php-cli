<?php

declare(strict_types=1);

namespace Package\Target;

use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Target;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Util\FileSystem;

#[Target('php-micro')]
class micro
{
    #[BeforeStage('php', [php::class, 'makeEmbedForUnix'], 'php-micro')]
    #[PatchDescription('Patch Makefile to build only libphp.la for embedding')]
    public function patchBeforeEmbed(TargetPackage $package): void
    {
        FileSystem::replaceFileStr("{$package->getSourceDir()}/Makefile", 'OVERALL_TARGET =', 'OVERALL_TARGET = libphp.la');
    }

    #[BeforeStage('php', [php::class, 'makeForUnix'], 'php-micro')]
    #[PatchDescription('Patch Makefile to skip installing micro binary')]
    public function patchMakefileBeforeUnixMake(TargetPackage $package): void
    {
        FileSystem::replaceFileStr("{$package->getSourceDir()}/Makefile", 'install-micro', '');
    }
}
