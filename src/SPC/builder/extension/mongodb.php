<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('mongodb')]
class mongodb extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--enable-mongodb --without-mongodb-sasl';
    }
}
