<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('ssh2')]
class ssh2 extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--with-ssh2';
    }
}
