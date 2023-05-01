<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('event')]
class event extends Extension
{
    public function getUnixConfigureArg(): string
    {
        $arg = '--with-event-core --with-event-extra --with-event-libevent-dir=' . BUILD_ROOT_PATH;
        if ($this->builder->getLib('openssl')) {
            $arg .= ' --with-event-openssl=' . BUILD_ROOT_PATH;
        }
        if ($this->builder->getExt('sockets')) {
            $arg .= ' --enable-event-sockets';
        } else {
            $arg .= ' --disable-event-sockets';
        }
        return $arg;
    }
}
