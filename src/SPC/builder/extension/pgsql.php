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
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function patchBeforeConfigure(): bool
    {
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
            return '--with-pgsql PGSQL_CFLAGS=-I' . BUILD_INCLUDE_PATH . ' PGSQL_LIBS="-L' . BUILD_LIB_PATH . ' -lpq -lpgport -lpgcommon"';
        }
        return '--with-pgsql=' . BUILD_ROOT_PATH;
    }

    /**
     * @throws WrongUsageException
     * @throws RuntimeException
     */
    public function getWindowsConfigureArg(): string
    {
        if ($this->builder->getPHPVersionID() >= 80400) {
            return '--with-pgsql';
        }
        return '--with-pgsql=' . BUILD_ROOT_PATH;
    }
}
