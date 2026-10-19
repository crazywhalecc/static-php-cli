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

#[Extension('clickhouse')]
class clickhouse extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer): string
    {
        $arg = '--enable-clickhouse' . ($shared ? '=shared' : '');
        if ($installer->getLibraryPackage('openssl')) {
            $arg .= ' --enable-clickhouse-openssl';
        }
        return $arg;
    }

    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-clickhouse')]
    #[PatchDescription('Remove Darwin -exported_symbol ldflag from clickhouse config.m4 that breaks static conftests')]
    public function patchBeforeBuildconf(): void
    {
        // upstream config.m4 appends -Wl,-exported_symbol,_get_module to the global LDFLAGS on
        // Darwin (meant for shared builds); in static in-tree builds this makes every later
        // configure conftest fail to link with "Undefined symbols: _get_module"
        if ($this->isBuildStatic()) {
            FileSystem::replaceFileStr(
                $this->getBuildDir() . '/config.m4',
                'LDFLAGS="$LDFLAGS -Wl,-exported_symbol,_get_module"',
                ''
            );
        }
    }
}
