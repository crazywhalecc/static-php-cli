<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('imap')]
class imap extends Extension
{
    public function getUnixConfigureArg(): string
    {
        $arg = '--with-imap=' . BUILD_ROOT_PATH;
        if ($this->builder->getLib('openssl') !== null) {
            $arg .= ' --with-imap-ssl=' . BUILD_ROOT_PATH;
        }
        return $arg;
    }
}
