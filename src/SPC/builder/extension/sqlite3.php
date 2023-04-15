<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('sqlite3')]
class sqlite3 extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--with-sqlite3="' . BUILD_ROOT_PATH . '" ' .
            'SQLITE_CFLAGS=-I"' . BUILD_INCLUDE_PATH . '" ' .
            'SQLITE_LIBS="' . $this->getLibFilesString() . '"';
    }
}
