<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\FileSystemException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('imap')]
class imap extends Extension
{
    public function getUnixConfigureArg(): string
    {
        $arg = '--with-imap=' . BUILD_ROOT_PATH;
        if ($this->builder->getLib('openssl') !== null) {
            $arg .= ' --with-imap-ssl=' . BUILD_ROOT_PATH;
        }
        return $arg;
    }

    /**
     * @throws FileSystemException
     */
    public function patchBeforeConfigure(): bool
    {
        if (!$this->builder->getLib('libpam')) {
            return true;
        }
        return (bool) FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/configure',
            'DLIBS="-l$IMAP_LIB $DLIBS"',
            'DLIBS="-l$IMAP_LIB $DLIBS -lpam"'
        );
    }
}
