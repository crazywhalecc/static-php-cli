<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

class Patcher
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public static function patchLinuxConfigHeader(string $libc): void
    {
        switch ($libc) {
            case 'musl_wrapper':
                // bad checks
                FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/main/php_config.h', '/^#define HAVE_STRLCPY 1$/m', '');
                FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/main/php_config.h', '/^#define HAVE_STRLCAT 1$/m', '');
                // no break
            case 'musl':
                FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/main/php_config.h', '/^#define HAVE_FUNC_ATTRIBUTE_IFUNC 1$/m', '');
                break;
            case 'glibc':
                // avoid lcrypt dependency
                FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/main/php_config.h', '/^#define HAVE_CRYPT 1$/m', '');
                FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/main/php_config.h', '/^#define HAVE_CRYPT_R 1$/m', '');
                FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/main/php_config.h', '/^#define HAVE_CRYPT_H 1$/m', '');
                break;
            default:
                throw new RuntimeException('not implemented');
        }
    }
}
