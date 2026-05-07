<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;

#[Extension('password-argon2')]
class password_argon2 extends PhpExtensionPackage
{
    public function runSmokeTestCliUnix(): void
    {
        [$ret] = shell()->execWithResult(BUILD_ROOT_PATH . '/bin/php -n -r "assert(defined(\'PASSWORD_ARGON2I\'));"');
        if ($ret !== 0) {
            throw new ValidationException('extension ' . $this->getName() . ' failed sanity check', validation_module: 'password_argon2 function check');
        }
    }

    #[CustomPhpConfigureArg('Linux')]
    #[CustomPhpConfigureArg('Darwin')]
    public function getConfigureArg(PackageInstaller $installer, PackageBuilder $builder): string
    {
        if ($installer->getLibraryPackage('openssl') !== null) {
            if (php::getPHPVersionID() >= 80500 || (php::getPHPVersionID() >= 80400 && !$builder->getOption('enable-zts'))) {
                return '--without-password-argon2'; // use --with-openssl-argon2 in openssl extension instead
            }
        }
        return '--with-password-argon2';
    }
}
