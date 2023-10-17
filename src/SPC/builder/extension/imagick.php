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
        // imagick may call omp_pause_all which requires -lgomp
        $extra_libs = $this->builder->getOption('extra-libs', '');
        $libf = BUILD_LIB_PATH;
        $extra_libs .= " {$libf}/libMagick++-7.Q16HDRI.a {$libf}/libMagickWand-7.Q16HDRI.a {$libf}/libMagickCore-7.Q16HDRI.a -lgomp ";
        $this->builder->setOption('extra-libs', $extra_libs);
        return true;
    }

    public function getUnixConfigureArg(): string
    {
        return '--with-imagick=' . BUILD_ROOT_PATH;
    }
}
