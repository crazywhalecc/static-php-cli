<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Extension('uv')]
class uv extends PhpExtensionPackage
{
    #[Validate]
    public function validate(): void
    {
        if (php::getPHPVersionID() < 80000 && getenv('SPC_SKIP_PHP_VERSION_CHECK') !== 'yes') {
            throw new ValidationException('The latest uv extension requires PHP 8.0 or later');
        }
    }

    #[BeforeStage('ext-uv', [PhpExtensionPackage::class, 'makeForUnix'])]
    public function patchBeforeSharedMake(PhpExtensionPackage $pkg): bool
    {
        if (SystemTarget::getTargetOS() !== 'Linux' || SystemTarget::getTargetArch() !== 'aarch64') {
            return false;
        }
        FileSystem::replaceFileRegex("{$pkg->getSourceDir()}/Makefile", '/^(LDFLAGS =.*)$/m', '$1 -luv -ldl -lrt -pthread');
        return true;
    }
}
