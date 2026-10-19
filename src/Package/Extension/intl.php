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
use StaticPHP\Util\GlobalEnvManager;

#[Extension('intl')]
class intl extends PhpExtensionPackage
{
    // php.h defines vsnprintf as ap_php_vsnprintf, breaking std::vsnprintf in libc++ <locale>
    #[BeforeStage('php', [php::class, 'makeForUnix'], 'ext-intl')]
    public function forceLibcxxLocaleBeforePhpHeaders(): void
    {
        $cxxflags = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CXXFLAGS') ?: '';
        if (!str_contains($cxxflags, '-include locale')) {
            GlobalEnvManager::putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CXXFLAGS=' . trim("{$cxxflags} -include locale"));
        }
    }

    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-intl')]
    #[PatchDescription('Fix intl config.w32: replace hardcoded true with PHP_INTL_SHARED for static build support; add /std:c++17 required by ICU 73+')]
    public function patchBeforeBuildconfForWindows(PackageInstaller $installer): void
    {
        $php_src = $installer->getTargetPackage('php')->getSourceDir();
        // Match only the tail of the EXTENSION() call: the source list changes between PHP
        // versions (8.6 added intl_icu_compat.c) and a missed replacement silently leaves the
        // hardcoded true, which builds intl shared and drops it from the static binary.
        FileSystem::replaceFileStr(
            "{$php_src}/ext/intl/config.w32",
            'intl_error.c ", true,',
            'intl_error.c ", PHP_INTL_SHARED,'
        );
        // ICU 73+ headers (char16ptr.h etc.) unconditionally include <string_view> which requires C++17.
        FileSystem::replaceFileStr(
            "{$php_src}/ext/intl/config.w32",
            'ADD_FLAG("CFLAGS_INTL", "/EHsc',
            'ADD_FLAG("CFLAGS_INTL", "/std:c++17 /EHsc'
        );
    }
}
