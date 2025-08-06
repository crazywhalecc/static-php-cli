<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixCMakeExecutor;

trait brotli
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->setBuildDir("{$this->getSourceDir()}/build-dir")
            ->addConfigureArgs("-DSHARE_INSTALL_PREFIX={$this->getBuildRootPath()}")
            ->build();

        $this->patchPkgconfPrefix(['libbrotlicommon.pc', 'libbrotlidec.pc', 'libbrotlienc.pc']);
        FileSystem::replaceFileLineContainsString(BUILD_LIB_PATH . '/pkgconfig/libbrotlidec.pc', 'Libs: -L${libdir} -lbrotlidec', 'Libs: -L${libdir} -lbrotlidec -lbrotlicommon');
        FileSystem::replaceFileLineContainsString(BUILD_LIB_PATH . '/pkgconfig/libbrotlienc.pc', 'Libs: -L${libdir} -lbrotlienc', 'Libs: -L${libdir} -lbrotlienc -lbrotlicommon');
        shell()->cd(BUILD_ROOT_PATH . '/lib')->exec('ln -sf libbrotlicommon.a libbrotli.a');
        foreach (FileSystem::scanDirFiles(BUILD_ROOT_PATH . '/lib/', false, true) as $filename) {
            if (str_starts_with($filename, 'libbrotli') && (str_contains($filename, '.so') || str_ends_with($filename, '.dylib'))) {
                unlink(BUILD_ROOT_PATH . '/lib/' . $filename);
            }
        }

        if (file_exists(BUILD_BIN_PATH . '/brotli')) {
            unlink(BUILD_BIN_PATH . '/brotli');
        }
    }
}
