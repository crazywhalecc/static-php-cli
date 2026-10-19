<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('spx')]
class spx extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Linux')]
    #[CustomPhpConfigureArg('Darwin')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer): string
    {
        $arg = '--enable-SPX' . ($shared ? '=shared' : '');
        if ($installer->getLibraryPackage('zlib') !== null) {
            $arg .= ' --with-zlib-dir=' . BUILD_ROOT_PATH;
        }
        return $arg;
    }

    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-spx')]
    #[PatchDescription('Fix spx extension compile error when building as static')]
    public function patchBeforeBuildconf(): bool
    {
        FileSystem::replaceFileStr(
            "{$this->getBuildDir()}/config.m4",
            'CFLAGS="$CFLAGS -Werror -Wall -O3 -pthread -std=gnu90"',
            'CFLAGS="$CFLAGS -pthread"'
        );
        FileSystem::replaceFileStr(
            "{$this->getBuildDir()}/src/php_spx.h",
            "extern zend_module_entry spx_module_entry;\n",
            "extern zend_module_entry spx_module_entry;;\n#define phpext_spx_ptr &spx_module_entry\n"
        );
        FileSystem::copy("{$this->getBuildDir()}/src/php_spx.h", "{$this->getBuildDir()}/php_spx.h");
        return true;
    }

    #[BeforeStage('php', [php::class, 'configureForUnix'], 'ext-spx')]
    #[PatchDescription('Fix spx extension compile error when configuring')]
    public function patchBeforeConfigure(): void
    {
        FileSystem::replaceFileStr(
            "{$this->getBuildDir()}/Makefile.frag",
            '@cp -r assets/web-ui/*',
            "@cp -r {$this->getBuildDir()}/assets/web-ui/*",
        );
    }

    public function getSharedExtensionEnv(): array
    {
        $env = parent::getSharedExtensionEnv();
        $env['SPX_SHARED_LIBADD'] = $env['LIBS'];
        return $env;
    }
}
