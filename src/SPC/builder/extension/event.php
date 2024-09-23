<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\macos\MacOSBuilder;
use SPC\exception\FileSystemException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('event')]
class event extends Extension
{
    public function getUnixConfigureArg(): string
    {
        $arg = '--with-event-core --with-event-extra --with-event-libevent-dir=' . BUILD_ROOT_PATH;
        if ($this->builder->getLib('openssl')) {
            $arg .= ' --with-event-openssl=' . BUILD_ROOT_PATH;
        }
        if ($this->builder->getExt('sockets')) {
            $arg .= ' --enable-event-sockets';
        } else {
            $arg .= ' --disable-event-sockets';
        }
        return $arg;
    }

    /**
     * @throws FileSystemException
     */
    public function patchBeforeConfigure(): bool
    {
        FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/configure', '/-levent_openssl/', $this->getLibFilesString());
        return true;
    }

    /**
     * @throws FileSystemException
     */
    public function patchBeforeMake(): bool
    {
        // Prevent event extension compile error on macOS
        if ($this->builder instanceof MacOSBuilder) {
            FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/main/php_config.h', '/^#define HAVE_OPENPTY 1$/m', '');
        }
        return true;
    }
}
