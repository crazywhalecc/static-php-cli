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

#[Extension('mongodb')]
class mongodb extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-mongodb')]
    #[PatchDescription('Add /utf-8 flag to CFLAGS_MONGODB for Windows build to fix compilation error on non-English Windows.')]
    public function patchBeforeBuild(): void
    {
        FileSystem::replaceFileStr(
            "{$this->getSourceDir()}/config.w32",
            'ADD_FLAG("CFLAGS_MONGODB", "/D KMS_MESSAGE_LITTLE_ENDIAN=1 /D MONGOCRYPT_LITTLE_ENDIAN=1 /D MLIB_USER=1");',
            'ADD_FLAG("CFLAGS_MONGODB", "/D KMS_MESSAGE_LITTLE_ENDIAN=1  /D MONGOCRYPT_LITTLE_ENDIAN=1 /D MLIB_USER=1");' . "\n    ADD_FLAG(\"CFLAGS_MONGODB\", \"/utf-8\");",
        );
    }

    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-mongodb')]
    #[PatchDescription('Replace src/libmongoc/ with ${ac_config_dir}/src/libmongoc/ in config.m4 to fix the build on Unix-like systems.')]
    public function patchBeforeBuildconfUnix(): void
    {
        FileSystem::replaceFileRegex(
            $this->getSourceDir() . '/config.m4',
            '/^(\s+)(src\/libmongoc\/)/m',
            '$1${ac_config_dir}/$2'
        );
    }

    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer): string
    {
        $arg = ' --enable-mongodb' . ($shared ? '=shared' : '') . ' ';
        $arg .= ' --with-mongodb-system-libs=no --with-mongodb-client-side-encryption=no ';
        $arg .= ' --with-mongodb-sasl=no ';
        if ($installer->getLibraryPackage('openssl')) {
            $arg .= '--with-mongodb-ssl=openssl';
        }
        $arg .= $installer->getLibraryPackage('icu') ? ' --with-mongodb-icu=yes ' : ' --with-mongodb-icu=no ';
        $arg .= $installer->getLibraryPackage('zstd') ? ' --with-mongodb-zstd=yes ' : ' --with-mongodb-zstd=no ';
        // $arg .= $installer->getLibraryPackage('snappy') ? ' --with-mongodb-snappy=yes ' : ' --with-mongodb-snappy=no ';
        $arg .= $installer->getLibraryPackage('zlib') ? ' --with-mongodb-zlib=yes ' : ' --with-mongodb-zlib=bundled ';
        return clean_spaces($arg);
    }

    public function getSharedExtensionEnv(): array
    {
        $parent = parent::getSharedExtensionEnv();
        $parent['CFLAGS'] .= ' -std=c17';
        return $parent;
    }
}
