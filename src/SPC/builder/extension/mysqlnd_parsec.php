<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('mysqlnd_parsec')]
class mysqlnd_parsec extends Extension
{
    public function getConfigureArg(bool $shared = false): string
    {
        return '--enable-mysqlnd_parsec' . ($shared ? '=shared' : '');
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        return $this->getConfigureArg();
    }
}
