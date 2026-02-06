<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\ValidationException;
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
            throw new ValidationException('extension ' . $this->getName() . ' failed sanity check', validation_module: 'password_argon2 function check');
        }
    }

    public function getConfigureArg(bool $shared = false): string
    {
        if ($this->builder->getLib('openssl') !== null) {
            if ($this->builder->getPHPVersionID() >= 80500 || ($this->builder->getPHPVersionID() >= 80400 && !$this->builder->getOption('enable-zts'))) {
                return '--without-password-argon2'; // use --with-openssl-argon2 in openssl extension instead
            }
        }
        return '--with-password-argon2';
    }
}
