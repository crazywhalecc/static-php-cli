<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\SPCConfigUtil;

#[Extension('pgsql')]
class pgsql extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageBuilder $builder, PackageInstaller $installer): string
    {
        if (php::getPHPVersionID() >= 80400) {
            $libfiles = new SPCConfigUtil(['libs_only_deps' => true, 'absolute_libs' => true])->getPackageDepsConfig('postgresql', array_keys($installer->getResolvedPackages()))['libs'];
            $libfiles = str_replace("{$builder->getLibDir()}/lib", '-l', $libfiles);
            $libfiles = str_replace('.a', '', $libfiles);
            return '--with-pgsql' . ($shared ? '=shared' : '') .
                ' PGSQL_CFLAGS=-I' . $builder->getIncludeDir() .
                ' PGSQL_LIBS="-L' . $builder->getLibDir() . ' ' . $libfiles . '"';
        }
        return '--with-pgsql=' . ($shared ? 'shared,' : '') . $builder->getBuildRootPath();
    }

    #[CustomPhpConfigureArg('Windows')]
    public function getWindowsConfigureArg(bool $shared, PackageBuilder $builder): string
    {
        if (php::getPHPVersionID() >= 80400) {
            return '--with-pgsql';
        }
        return "--with-pgsql={$builder->getBuildRootPath()}";
    }

    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-pgsql')]
    #[PatchDescription('Link the Win32 system libraries the static libpq needs')]
    public function patchConfigW32ForWindows(TargetPackage $package): void
    {
        $config = "{$package->getSourceDir()}\\ext\\pgsql\\config.w32";

        if (str_contains(FileSystem::readFile($config), 'LIBS_PGSQL')) {
            return;
        }

        // libpq uses SSPI for Windows auth (secur32) and the static libcrypto behind it
        // reads the system cert store (crypt32). Nothing else puts these on the link line
        // when the openssl extension itself is not part of the build.
        FileSystem::replaceFileStr(
            $config,
            'EXTENSION("pgsql", "pgsql.c", PHP_PGSQL_SHARED, "/DZEND_ENABLE_STATIC_TSRMLS_CACHE=1");',
            'EXTENSION("pgsql", "pgsql.c", PHP_PGSQL_SHARED, "/DZEND_ENABLE_STATIC_TSRMLS_CACHE=1");' . "\n\t\t" .
            'ADD_FLAG("LIBS_PGSQL", "secur32.lib crypt32.lib");'
        );
    }

    public function getSharedExtensionEnv(): array
    {
        $parent = parent::getSharedExtensionEnv();
        $parent['CFLAGS'] .= ' -std=c17 -Wno-int-conversion';
        return $parent;
    }
}
