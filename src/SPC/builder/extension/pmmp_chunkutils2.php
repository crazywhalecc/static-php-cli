<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('pmmp-chunkutils2')]
class pmmp_chunkutils2 extends Extension
{
    public function getDistName(): string
    {
        return 'chunkutils2';
    }

    public function getUnixConfigureArg(): string
    {
        return '--enable-chunkutils2';
    }
}
