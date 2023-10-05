<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('imagick')]
class imagick extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        // linux need to link library manually, we add it to extra-libs
        $extra_libs = $this->builder->getOption('extra-libs', '');
        if (!str_contains($extra_libs, 'libMagickCore')) {
            $extra_libs .= ' /usr/lib/libMagick++-7.Q16HDRI.a /usr/lib/libMagickCore-7.Q16HDRI.a /usr/lib/libMagickWand-7.Q16HDRI.a ';
        }
        $extra_libs .= $this->builder->getLib('libzip') ? '-lzip ' : '';
        $extra_libs .= $this->builder->getLib('libjpeg') ? '-ljpeg ' : '';
        $extra_libs .= $this->builder->getLib('libpng') ? '-lpng ' : '';
        $extra_libs .= $this->builder->getLib('libwebp') ? '-lwebp ' : '';
        $extra_libs .= $this->builder->getLib('zstd') ? '-lzstd ' : '';
        $extra_libs .= $this->builder->getLib('freetype') ? '-lfreetype ' : '';
        $this->builder->setOption('extra-libs', $extra_libs);
        return true;
    }

    public function getUnixConfigureArg(): string
    {
        return '--with-imagick=' . BUILD_ROOT_PATH;
    }
}
