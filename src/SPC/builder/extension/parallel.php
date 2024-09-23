<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\WrongUsageException;
use SPC\util\CustomExt;

#[CustomExt('parallel')]
class parallel extends Extension
{
    public function validate(): void
    {
        if (!$this->builder->getOption('enable-zts')) {
            throw new WrongUsageException('ext-parallel must be built with ZTS builds. Use "--enable-zts" option!');
        }
    }
}
