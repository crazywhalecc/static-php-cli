<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('ffi')]
class ffi extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--with-ffi FFI_CFLAGS=-I"' . BUILD_INCLUDE_PATH . '" ' .
            'FFI_LIBS="' . $this->getLibFilesString() . '"';
    }
}
