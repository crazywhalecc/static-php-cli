<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('brotli')]
class brotli extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        return $this->getEnableArg($shared) . '--with-libbrotli';
    }
}
