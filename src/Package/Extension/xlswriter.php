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
use StaticPHP\Util\SourcePatcher;

#[Extension('xlswriter')]
class xlswriter extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer): string
    {
        $shared = $shared ? '=shared' : '';
        $arg = "--with-xlswriter{$shared} --enable-reader";
        if ($installer->getLibraryPackage('openssl')) {
            $arg .= ' --with-openssl=' . $installer->getLibraryPackage('openssl')->getBuildRootPath();
        }
        return $arg;
    }

    #[BeforeStage('php', [php::class, 'makeForWindows'], 'ext-xlswriter')]
    #[PatchDescription('Fix Windows build: apply win32 patch and add UTF-8 BOM to theme.c')]
    public function patchBeforeMakeForWindows(): void
    {
        $source_dir = $this->getSourceDir();
        $theme_file = "{$source_dir}/library/libxlsx/src/theme.c";

        // fix windows build with openssl extension duplicate symbol bug
        SourcePatcher::patchFile('spc_fix_xlswriter_win32.patch', $source_dir);
        $content = file_get_contents($theme_file);
        $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
        if (!str_starts_with($content, $bom)) {
            file_put_contents($theme_file, $bom . $content);
        }
    }
}
