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
        $extra_libs = $this->builder->getOption('extra-libs', '');
        $LIB_PATH = BUILD_LIB_PATH;
        // always add these even if they aren't needed, php-imagick pulls MagickWand before MagickCore which leads to undefined references
        $extra_libs .= " {$LIB_PATH}/libMagick++-7.Q16HDRI.a {$LIB_PATH}/libMagickWand-7.Q16HDRI.a  {$LIB_PATH}/libMagickCore-7.Q16HDRI.a ";
        $extra_libs .= $this->builder->getLib('libzip') ? "{$LIB_PATH}/libzip.a " : '';
        $extra_libs .= $this->builder->getLib('libjpeg') ? "{$LIB_PATH}/libjpeg.a " : '';
        $extra_libs .= $this->builder->getLib('libpng') ? "{$LIB_PATH}/libpng.a " : '';
        $extra_libs .= $this->builder->getLib('libwebp') ? "{$LIB_PATH}/libwebp.a " : '';
        $extra_libs .= $this->builder->getLib('zstd') ? "{$LIB_PATH}/libzstd.a " : '';
        $extra_libs .= $this->builder->getLib('freetype') ? "{$LIB_PATH}/libfreetype.a " : '';
        $this->builder->setOption('extra-libs', $extra_libs);
        return true;
    }

    public function patchBeforeMake(): bool
    {
        $extra_libs = $this->builder->getOption('extra-libs', '');
        $LIB_PATH = BUILD_LIB_PATH;
        // always add these even if they aren't needed, php-imagick pulls MagickWand before MagickCore which leads to undefined references
        $extra_libs .= " {$LIB_PATH}/libMagick++-7.Q16HDRI.a {$LIB_PATH}/libMagickWand-7.Q16HDRI.a  {$LIB_PATH}/libMagickCore-7.Q16HDRI.a ";
        $extra_libs .= '-lgomp ';
        $this->builder->setOption('extra-libs', $extra_libs);
        return true;
    }

    public function getUnixConfigureArg(): string
    {
        return '--with-imagick=' . BUILD_ROOT_PATH;
    }
}
