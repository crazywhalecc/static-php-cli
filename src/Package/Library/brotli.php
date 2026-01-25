<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('brotli')]
class brotli
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixCMakeExecutor::create($lib)
            ->setBuildDir($lib->getSourceDir() . '/build-dir')
            ->addConfigureArgs("-DSHARE_INSTALL_PREFIX={$lib->getBuildRootPath()}")
            ->build();

        // Patch pkg-config files
        $lib->patchPkgconfPrefix(['libbrotlicommon.pc', 'libbrotlidec.pc', 'libbrotlienc.pc'], PKGCONF_PATCH_PREFIX);

        // Add -lbrotlicommon to libbrotlidec.pc and libbrotlienc.pc
        FileSystem::replaceFileLineContainsString(
            $lib->getLibDir() . '/pkgconfig/libbrotlidec.pc',
            'Libs: -L${libdir} -lbrotlidec',
            'Libs: -L${libdir} -lbrotlidec -lbrotlicommon'
        );
        FileSystem::replaceFileLineContainsString(
            $lib->getLibDir() . '/pkgconfig/libbrotlienc.pc',
            'Libs: -L${libdir} -lbrotlienc',
            'Libs: -L${libdir} -lbrotlienc -lbrotlicommon'
        );

        // Create symlink: libbrotli.a -> libbrotlicommon.a
        shell()->cd($lib->getLibDir())->exec('ln -sf libbrotlicommon.a libbrotli.a');

        // Remove dynamic libraries
        foreach (FileSystem::scanDirFiles($lib->getLibDir(), false, true) as $filename) {
            if (str_starts_with($filename, 'libbrotli') && (str_contains($filename, '.so') || str_ends_with($filename, '.dylib'))) {
                unlink($lib->getLibDir() . '/' . $filename);
            }
        }

        // Remove brotli binary if exists
        if (file_exists($lib->getBinDir() . '/brotli')) {
            unlink($lib->getBinDir() . '/brotli');
        }
    }
}
