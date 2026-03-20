<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('yac')]
class yac extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-yac')]
    public function patchBeforeBuildconf(PackageInstaller $installer): bool
    {
        FileSystem::replaceFileStr("{$this->getSourceDir()}/storage/allocator/yac_allocator.h", 'defined(HAVE_SHM_MMAP_ANON)', 'defined(YAC_ALLOCATOR_H)');
        FileSystem::replaceFileStr("{$this->getSourceDir()}/serializer/igbinary.c", '#ifdef YAC_ENABLE_IGBINARY', '#if 1');
        FileSystem::replaceFileStr("{$this->getSourceDir()}/serializer/json.c", '#if YAC_ENABLE_JSON', '#if 1');
        return true;
    }
}
