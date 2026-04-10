<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('xz')]
class xz extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-xz')]
    public function patchBeforeBuildconf(): void
    {
        FileSystem::replaceFileStr($this->getSourceDir() . '/config.w32', 'true', 'PHP_XZ_SHARED');
    }
}
