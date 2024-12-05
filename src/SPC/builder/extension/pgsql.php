<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('pgsql')]
class pgsql extends Extension
{
    /**
     * @return bool
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function patchBeforeConfigure(): bool
    {
        if ($this->builder->getPHPVersionID() >= 80400) {
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/configure',
                'LIBS="-lpq',
                'LIBS="-lpq -lpgport -lpgcommon'
            );
            return true;
        }
        FileSystem::replaceFileRegex(
            SOURCE_PATH . '/php-src/configure',
            '/-lpq/',
            $this->getLibFilesString()
        );
        return true;
    }

    /**
     * @throws WrongUsageException
     * @throws RuntimeException
     */
    public function getUnixConfigureArg(): string
    {
        if ($this->builder->getPHPVersionID() >= 80400) {
            return '--with-pgsql=' . BUILD_ROOT_PATH . ' PGSQL_CFLAGS=-I' . BUILD_INCLUDE_PATH . ' PGSQL_LIBS="-L' . BUILD_LIB_PATH . ' -lpq -lpgport -lpgcommon"';
        }
        return '--with-pgsql=' . BUILD_ROOT_PATH;
    }
}
