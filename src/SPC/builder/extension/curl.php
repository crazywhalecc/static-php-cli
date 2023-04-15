<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('curl')]
class curl extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--with-curl CURL_CFLAGS=-I"' . BUILD_INCLUDE_PATH . '" ' .
            'CURL_LIBS="' . $this->getLibFilesString() . '"';
    }
}
