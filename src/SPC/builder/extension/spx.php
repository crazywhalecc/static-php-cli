<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\WrongUsageException;
use SPC\util\CustomExt;

#[CustomExt('spx')]
class spx extends Extension
{
    /**
     * @throws WrongUsageException
     */
    public function validate(): void
    {
        if ($this->builder->getOption('enable-zts')) {
            throw new WrongUsageException('ext-spx is not thread safe, do not build it with ZTS builds');
        }
    }

    public function getUnixConfigureArg(): string
    {
        $arg = '--enable-spx';
        if ($this->builder->getExt('zlib') === null) {
            $arg .= ' --with-zlib-dir=' . BUILD_ROOT_PATH;
        }
        return $arg;
    }
}
