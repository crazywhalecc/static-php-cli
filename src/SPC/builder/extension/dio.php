<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('dio')]
class dio extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        if (!file_exists(SOURCE_PATH . '/php-src/ext/dio/php_dio.h')) {
            FileSystem::writeFile(SOURCE_PATH . '/php-src/ext/dio/php_dio.h', FileSystem::readFile(SOURCE_PATH . '/php-src/ext/dio/src/php_dio.h'));
            return true;
        }
        return false;
    }
}
