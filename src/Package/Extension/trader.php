<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('trader')]
class trader extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-trader')]
    public function patchBeforeBuildconf(PackageInstaller $installer): bool
    {
        FileSystem::replaceFileStr("{$this->getSourceDir()}/config.m4", 'PHP_TA', 'PHP_TRADER');
        return true;
    }
}
