<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\RuntimeException;
use SPC\util\CustomExt;

#[CustomExt('password-argon2')]
class password_argon2 extends Extension
{
    public function getDistName(): string
    {
        return '';
    }

    public function runCliCheckUnix(): void
    {
        [$ret] = shell()->execWithResult(BUILD_ROOT_PATH . '/bin/php -n -r "assert(defined(\'PASSWORD_ARGON2I\'));"');
        if ($ret !== 0) {
            throw new RuntimeException('extension ' . $this->getName() . ' failed sanity check');
        }
    }
}
