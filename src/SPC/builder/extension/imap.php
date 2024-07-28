<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('imap')]
class imap extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        if ($this->builder->getLib('openssl')) {
            // sometimes imap with openssl does not contain zlib (required by openssl)
            // we need to add it manually
            FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/imap/config.m4', 'TST_LIBS="$DLIBS $IMAP_SHARED_LIBADD"', 'TST_LIBS="$DLIBS $IMAP_SHARED_LIBADD -lz"');
            return true;
        }
        return false;
    }

    /**
     * @throws WrongUsageException
     */
    public function validate(): void
    {
        if ($this->builder->getOption('enable-zts')) {
            throw new WrongUsageException('ext-imap is not thread safe, do not build it with ZTS builds');
        }
    }

    public function getUnixConfigureArg(): string
    {
        $arg = '--with-imap=' . BUILD_ROOT_PATH;
        if ($this->builder->getLib('openssl') !== null) {
            $arg .= ' --with-imap-ssl=' . BUILD_ROOT_PATH;
        }
        return $arg;
    }
}
