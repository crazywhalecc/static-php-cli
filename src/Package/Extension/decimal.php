<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('decimal')]
class decimal extends PhpExtensionPackage
{
    // TODO: remove this when https://github.com/php-decimal/ext-decimal/issues/92 is merged
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-decimal')]
    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-decimal')]
    #[PatchDescription('Fix decimal extension module entry symbol name conflict')]
    public function patchBeforeBuildconf(): void
    {
        FileSystem::replaceFileStr(
            $this->getSourceDir() . '/php_decimal.c',
            'zend_module_entry decimal_module_entry',
            'zend_module_entry php_decimal_module_entry'
        );
    }

    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-decimal')]
    #[PatchDescription('Ensure ext/json MINIT runs before ext/decimal on Windows static builds')]
    public function patchConfigW32(): void
    {
        FileSystem::replaceFileStr(
            $this->getSourceDir() . '/config.w32',
            'ARG_WITH("decimal", "for decimal support", "no");',
            'ARG_WITH("decimal", "for decimal support",  "no");' . "\n" .
            'ADD_EXTENSION_DEP("decimal", "json");'
        );
    }
}
