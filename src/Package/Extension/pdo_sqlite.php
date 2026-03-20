<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Util\FileSystem;

#[Extension('pdo_sqlite')]
class pdo_sqlite
{
    #[BeforeStage('php', [php::class, 'configureForUnix'], 'ext-pdo_sqlite')]
    public function patchBeforeConfigure(PackageInstaller $installer): void
    {
        FileSystem::replaceFileRegex(
            "{$installer->getTargetPackage('php')->getSourceDir()}/configure",
            '/sqlite3_column_table_name=yes/',
            'sqlite3_column_table_name=no'
        );
    }
}
