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
        $arg .= ' --with-jpeg --with-freetype --with-webp';
        return $arg;
    }
}
