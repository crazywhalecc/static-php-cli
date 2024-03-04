<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('amqp')]
class amqp extends Extension
{
    public function patchBeforeMake(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            FileSystem::replaceFileRegex(BUILD_INCLUDE_PATH . '\amqp.h', '/^#warning.*/m', '');
            FileSystem::replaceFileRegex(BUILD_INCLUDE_PATH . '\amqp_framing.h', '/^#warning.*/m', '');
            FileSystem::replaceFileRegex(BUILD_INCLUDE_PATH . '\amqp_ssl_socket.h', '/^#warning.*/m', '');
            FileSystem::replaceFileRegex(BUILD_INCLUDE_PATH . '\amqp_tcp_socket.h', '/^#warning.*/m', '');
            return true;
        }
        return false;
    }

    public function getUnixConfigureArg(): string
    {
        return '--with-amqp --with-librabbitmq-dir=' . BUILD_ROOT_PATH;
    }

    public function getWindowsConfigureArg(): string
    {
        return '--with-amqp';
    }
}
