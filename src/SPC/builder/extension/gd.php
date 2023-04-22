<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('gd')]
class gd extends Extension
{
    public function getUnixConfigureArg(): string
    {
        $arg = '--enable-gd';
        if ($this->builder->getLib('freetype')) {
            $arg .= ' --with-freetype ' .
                'FREETYPE2_CFLAGS=-I"' . BUILD_INCLUDE_PATH . '/freetype2" ' .
                'FREETYPE2_LIBS="' . $this->getLibFilesString() . '"';
        }
        $arg .= ' PNG_CFLAGS=-I"' . BUILD_INCLUDE_PATH . '" ' .
            'PNG_LIBS="' . $this->getLibFilesString() . '"';
        $arg .= ' --with-jpeg --with-freetype --with-webp';
        return $arg;
    }
}
