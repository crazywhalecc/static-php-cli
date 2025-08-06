<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixCMakeExecutor;

trait freetype
{
    protected function build(): void
    {
        $cmake = UnixCMakeExecutor::create($this)
            ->optionalLib('libpng', ...cmake_boolean_args('FT_DISABLE_PNG', true))
            ->optionalLib('bzip2', ...cmake_boolean_args('FT_DISABLE_BZIP2', true))
            ->optionalLib('brotli', ...cmake_boolean_args('FT_DISABLE_BROTLI', true))
            ->addConfigureArgs('-DFT_DISABLE_HARFBUZZ=ON');

        // fix cmake 4.0 compatibility
        if (version_compare(get_cmake_version(), '4.0.0', '>=')) {
            $cmake->addConfigureArgs('-DCMAKE_POLICY_VERSION_MINIMUM=3.12');
        }

        $cmake->build();

        $this->patchPkgconfPrefix(['freetype2.pc']);
        FileSystem::replaceFileStr(
            BUILD_ROOT_PATH . '/lib/pkgconfig/freetype2.pc',
            ' -L/lib ',
            ' -L' . BUILD_ROOT_PATH . '/lib '
        );
    }
}
