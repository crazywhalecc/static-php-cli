<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\WrongUsageException;
use SPC\util\CustomExt;

#[CustomExt('imap')]
class imap extends Extension
{
    /**
     * @throws WrongUsageException
     */
    public function getUnixConfigureArg(): string
    {
        if ($this->builder->getOption('enable-zts')) {
            throw new WrongUsageException('ext-imap is not thread safe, do not build it with ZTS builds');
        }
        $arg = '--with-imap=' . BUILD_ROOT_PATH;
        if ($this->builder->getLib('openssl') !== null) {
            $arg .= ' --with-imap-ssl=' . BUILD_ROOT_PATH;
        }
        return $arg;
    }
}
