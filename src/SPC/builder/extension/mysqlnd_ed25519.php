<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('mysqlnd_ed25519')]
class mysqlnd_ed25519 extends Extension
{
    public function getConfigureArg(bool $shared = false): string
    {
        return '--with-mysqlnd_ed25519' . ($shared ? '=shared' : '');
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        return $this->getConfigureArg();
    }
}
