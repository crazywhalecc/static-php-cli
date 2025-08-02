<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('openssl')]
class openssl extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        // Fix php 8.5 alpha1~4 zts openssl build bug
        FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/ext/openssl/config.w32',
            'WARNING("OpenSSL argon2 hashing not supported in ZTS mode for now");',
            'AC_DEFINE("HAVE_OPENSSL_ARGON2", 1, "Define to 1 to enable OpenSSL argon2 password hashing.");'
        );
        return true;
    }

    public function patchBeforeMake(): bool
    {
        $patched = parent::patchBeforeMake();
        // patch openssl3 with php8.0 bug
        if ($this->builder->getPHPVersionID() < 80100) {
            $openssl_c = file_get_contents(SOURCE_PATH . '/php-src/ext/openssl/openssl.c');
            $openssl_c = preg_replace('/REGISTER_LONG_CONSTANT\s*\(\s*"OPENSSL_SSLV23_PADDING"\s*.+;/', '', $openssl_c);
            file_put_contents(SOURCE_PATH . '/php-src/ext/openssl/openssl.c', $openssl_c);
            return true;
        }

        return $patched;
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        $openssl_dir = $this->builder->getPHPVersionID() >= 80400 ? '' : ' --with-openssl-dir=' . BUILD_ROOT_PATH;
        $args = '--with-openssl=' . ($shared ? 'shared,' : '') . BUILD_ROOT_PATH . $openssl_dir;
        if ($this->builder->getPHPVersionID() >= 80500) {
            $args .= ' --with-openssl-argon2 OPENSSL_LIBS="-lz"';
        }
        return $args;
    }

    public function getWindowsConfigureArg(bool $shared = false): string
    {
        $args = '--with-openssl';
        if ($this->builder->getPHPVersionID() >= 80500) {
            $args .= ' --with-openssl-argon2';
        }
        return $args;
    }
}
