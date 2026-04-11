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
use StaticPHP\Util\GlobalEnvManager;
use StaticPHP\Util\SourcePatcher;

#[Extension('xlswriter')]
class xlswriter extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer): string
    {
        $arg = '--with-xlswriter --enable-reader';
        if ($installer->getLibraryPackage('openssl')) {
            $arg .= ' --with-openssl=' . $installer->getLibraryPackage('openssl')->getBuildRootPath();
        }
        return $arg;
    }

    #[BeforeStage('php', [php::class, 'makeForUnix'], 'ext-xlswriter')]
    #[PatchDescription('Fix Unix build: add -std=gnu17 to CFLAGS to fix build errors on older GCC versions')]
    public function patchBeforeUnixMake(): void
    {
        GlobalEnvManager::putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS=' . getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') . ' -std=gnu17');
    }

    #[BeforeStage('php', [php::class, 'makeForWindows'], 'ext-xlswriter')]
    #[PatchDescription('Fix Windows build: apply win32 patch and add UTF-8 BOM to theme.c')]
    public function patchBeforeMakeForWindows(): void
    {
        // fix windows build with openssl extension duplicate symbol bug
        SourcePatcher::patchFile('spc_fix_xlswriter_win32.patch', $this->getSourceDir());
        $content = file_get_contents($this->getSourceDir() . '/library/libxlsxwriter/src/theme.c');
        $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
        if (!str_starts_with($content, $bom)) {
            file_put_contents($this->getSourceDir() . '/library/libxlsxwriter/src/theme.c', $bom . $content);
        }
    }

    public function getSharedExtensionEnv(): array
    {
        $parent = parent::getSharedExtensionEnv();
        $parent['CFLAGS'] .= ' -std=gnu17';
        return $parent;
    }
}
