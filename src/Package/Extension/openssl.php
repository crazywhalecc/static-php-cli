<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PackageBuilder;

#[Extension('openssl')]
class openssl
{
    #[BeforeStage('php', [php::class, 'makeForUnix'], 'ext-openssl')]
    #[PatchDescription('Patch OpenSSL extension for PHP 8.0 compatibility with OpenSSL 3')]
    public function patchBeforeMake(): void
    {
        // patch openssl3 with php8.0 bug
        if (php::getPHPVersionID() < 80100) {
            $openssl_c = file_get_contents(SOURCE_PATH . '/php-src/ext/openssl/openssl.c');
            $openssl_c = preg_replace('/REGISTER_LONG_CONSTANT\s*\(\s*"OPENSSL_SSLV23_PADDING"\s*.+;/', '', $openssl_c);
            file_put_contents(SOURCE_PATH . '/php-src/ext/openssl/openssl.c', $openssl_c);
        }
    }

    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(PackageBuilder $builder, bool $shared = false): string
    {
        $openssl_dir = php::getPHPVersionID() >= 80400 ? '' : ' --with-openssl-dir=' . BUILD_ROOT_PATH;
        $args = '--with-openssl=' . ($shared ? 'shared,' : '') . BUILD_ROOT_PATH . $openssl_dir;
        if (php::getPHPVersionID() >= 80500 || (php::getPHPVersionID() >= 80400 && !$builder->getOption('enable-zts'))) {
            $args .= ' --with-openssl-argon2 OPENSSL_LIBS="-lz"';
        }
        return $args;
    }

    #[CustomPhpConfigureArg('Windows')]
    public function getWindowsConfigureArg(PackageBuilder $builder): string
    {
        $args = '--with-openssl';
        if (php::getPHPVersionID() >= 80500 || (php::getPHPVersionID() >= 80400 && !$builder->getOption('enable-zts'))) {
            $args .= ' --with-openssl-argon2';
        }
        return $args;
    }
}
