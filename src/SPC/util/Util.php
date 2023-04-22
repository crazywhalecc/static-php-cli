<?php

declare(strict_types=1);

namespace SPC\util;

class Util
{
    /**
     * Get current PHP version ID (downloaded)
     */
    public static function getPHPVersionID(): int
    {
        $file = file_get_contents(SOURCE_PATH . '/php-src/main/php_version.h');
        preg_match('/PHP_VERSION_ID (\d+)/', $file, $match);
        return intval($match[1]);
    }
}
