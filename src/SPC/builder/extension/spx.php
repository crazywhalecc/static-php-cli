<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('spx')]
class spx extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        $arg = '--enable-spx' . ($shared ? '=shared' : '');
        if ($this->builder->getExt('zlib') === null) {
            $arg .= ' --with-zlib-dir=' . BUILD_ROOT_PATH;
        }
        return $arg;
    }
}
