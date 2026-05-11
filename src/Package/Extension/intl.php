<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('intl')]
class intl extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-intl')]
    #[PatchDescription('Fix intl config.w32: replace hardcoded true with PHP_INTL_SHARED for static build support; add /std:c++17 required by ICU 73+')]
    public function patchBeforeBuildconfForWindows(PackageInstaller $installer): void
    {
        $php_src = $installer->getTargetPackage('php')->getSourceDir();
        FileSystem::replaceFileStr(
            "{$php_src}/ext/intl/config.w32",
            'EXTENSION("intl", "php_intl.c intl_convert.c intl_convertcpp.cpp intl_error.c ", true,',
            'EXTENSION("intl", "php_intl.c intl_convert.c intl_convertcpp.cpp intl_error.c ", PHP_INTL_SHARED,'
        );
        // ICU 73+ headers (char16ptr.h etc.) unconditionally include <string_view> which requires C++17.
        FileSystem::replaceFileStr(
            "{$php_src}/ext/intl/config.w32",
            'ADD_FLAG("CFLAGS_INTL", "/EHsc',
            'ADD_FLAG("CFLAGS_INTL", "/std:c++17 /EHsc'
        );
    }
}
