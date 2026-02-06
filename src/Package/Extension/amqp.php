<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Util\FileSystem;

#[Extension('amqp')]
class amqp
{
    #[BeforeStage('php', [php::class, 'makeForWindows'], 'ext-amqp')]
    #[PatchDescription('Remove #warning directives from amqp headers to prevent build failures on Windows')]
    public function patchBeforeMake(): bool
    {
        FileSystem::replaceFileRegex(BUILD_INCLUDE_PATH . '\amqp.h', '/^#warning.*/m', '');
        FileSystem::replaceFileRegex(BUILD_INCLUDE_PATH . '\amqp_framing.h', '/^#warning.*/m', '');
        FileSystem::replaceFileRegex(BUILD_INCLUDE_PATH . '\amqp_ssl_socket.h', '/^#warning.*/m', '');
        FileSystem::replaceFileRegex(BUILD_INCLUDE_PATH . '\amqp_tcp_socket.h', '/^#warning.*/m', '');
        return true;
    }
}
