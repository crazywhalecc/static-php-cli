<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('parallel')]
class parallel extends PhpExtensionPackage
{
    #[Validate]
    public function validate(PackageBuilder $builder): void
    {
        if (!$builder->getOption('enable-zts')) {
            throw new WrongUsageException('ext-parallel must be built with ZTS builds. Use "--enable-zts" option!');
        }
    }

    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-parallel')]
    #[PatchDescription('Fix parallel m4 hardcoded PHP_VERSION check')]
    public function patchBeforeBuildconf(): bool
    {
        FileSystem::replaceFileRegex("{$this->getSourceDir()}/config.m4", '/PHP_VERSION=.*/m', '');
        return true;
    }
}
