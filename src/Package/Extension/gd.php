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

#[Extension('gd')]
class gd extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-gd')]
    #[PatchDescription('Fix libgd iconv_t fallback typedef guard for shared builds (PHP 8.6+)')]
    public function patchBeforeBuildconf(): void
    {
        FileSystem::replaceFileStr(
            "{$this->getBuildDir()}/libgd/gdkanji.c",
            "#ifndef HAVE_ICONV_T_DEF\ntypedef void *iconv_t;",
            "#ifndef HAVE_ICONV\ntypedef void *iconv_t;",
        );
    }

    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer): string
    {
        $arg = '--enable-gd' . ($shared ? '=shared' : '');
        $arg .= $installer->getLibraryPackage('freetype') ? ' --with-freetype' : '';
        $arg .= $installer->getLibraryPackage('libjpeg') ? ' --with-jpeg' : '';
        $arg .= $installer->getLibraryPackage('libwebp') ? ' --with-webp' : '';
        $arg .= $installer->getLibraryPackage('libavif') ? ' --with-avif' : '';
        return $arg;
    }
}
