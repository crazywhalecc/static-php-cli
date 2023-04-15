<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('openssl')]
class openssl extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--with-openssl OPENSSL_CFLAGS=-I"' . BUILD_INCLUDE_PATH . '" ' .
            'OPENSSL_LIBS="' . $this->getLibFilesString() . '" ';
    }
}
