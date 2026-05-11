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
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-clickhouse')]
    #[PatchDescription('Replace THIS_DIR=`dirname $0` with PHP_EXT_SRCDIR() in config.m4 so include paths resolve to the ext source dir during PHP main configure (dirname $0 returns "." when run from php-src root).')]
    public function patchBeforeBuildconfUnix(): void
    {
        FileSystem::replaceFileRegex(
            "{$this->getSourceDir()}/config.m4",
            '/^(\s*)THIS_DIR=.*/m',
            '$1THIS_DIR=PHP_EXT_SRCDIR()',
        );
    }

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
}
