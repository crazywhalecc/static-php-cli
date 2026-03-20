<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('dio')]
class dio extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-dio')]
    public function patchBeforeBuildconf(): void
    {
        if (!file_exists("{$this->getSourceDir()}/php_dio.h")) {
            FileSystem::writeFile("{$this->getSourceDir()}/php_dio.h", FileSystem::readFile("{$this->getSourceDir()}/src/php_dio.h"));
        }
    }
}
