<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

trait libavif
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->optionalLib('libaom', '-DAVIF_CODEC_AOM=SYSTEM', '-DAVIF_CODEC_AOM=OFF')
            ->optionalLib('libsharpyuv', '-DAVIF_LIBSHARPYUV=SYSTEM', '-DAVIF_LIBSHARPYUV=OFF')
            ->optionalLib('libjpeg', '-DAVIF_JPEG=SYSTEM', '-DAVIF_JPEG=OFF')
            ->optionalLib('libxml2', '-DAVIF_LIBXML2=SYSTEM', '-DAVIF_LIBXML2=OFF')
            ->optionalLib('libpng', '-DAVIF_LIBPNG=SYSTEM', '-DAVIF_LIBPNG=OFF')
            ->addConfigureArgs('-DAVIF_LIBYUV=OFF')
            ->build();
        // patch pkgconfig
        $this->patchPkgconfPrefix(['libavif.pc']);
    }
}
