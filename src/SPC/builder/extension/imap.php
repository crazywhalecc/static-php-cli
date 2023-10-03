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
            return false;
        }
        $extra_libs = $this->builder->getOption('extra-libs', '');
        if (!str_contains($extra_libs, 'lpam') && !str_contains($extra_libs, 'libpam.a')) {
            $extra_libs .= ' ' . BUILD_LIB_PATH . '/libpam.a';
        }
        $this->builder->setOption('extra-libs', $extra_libs);
        return (bool) FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/configure',
            'DLIBS="-l$IMAP_LIB $DLIBS"',
            'DLIBS="-l$IMAP_LIB $DLIBS -lpam"'
        );
    }
}
