<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\FileSystemException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('pdo_sqlite')]
class pdo_sqlite extends Extension
{
    /**
     * @throws FileSystemException
     */
    public function patchBeforeConfigure(): bool
    {
        FileSystem::replaceFile(
            SOURCE_PATH . '/php-src/configure',
            REPLACE_FILE_PREG,
            '/sqlite3_column_table_name=yes/',
            'sqlite3_column_table_name=no'
        );
        return true;
    }
}
