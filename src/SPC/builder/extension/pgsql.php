<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\FileSystemException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('pgsql')]
class pgsql extends Extension
{
    /**
     * @throws FileSystemException
     */
    public function patchBeforeConfigure(): bool
    {
        FileSystem::replaceFile(
            SOURCE_PATH . '/php-src/configure',
            REPLACE_FILE_PREG,
            '/-lpq/',
            $this->getLibFilesString()
        );
        return true;
    }
}
