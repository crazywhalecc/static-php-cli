<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('memcache')]
class memcache extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--enable-memcache --with-zlib-dir=' . BUILD_ROOT_PATH;
    }

    public function patchBeforeBuildconf(): bool
    {
        FileSystem::replaceFile(
            SOURCE_PATH . '/php-src/ext/memcache/config9.m4',
            REPLACE_FILE_STR,
            'if test -d $abs_srcdir/src ; then',
            'if test -d $abs_srcdir/main ; then'
        );
        FileSystem::replaceFile(
            SOURCE_PATH . '/php-src/ext/memcache/config9.m4',
            REPLACE_FILE_STR,
            'export CPPFLAGS="$CPPFLAGS $INCLUDES"',
            'export CPPFLAGS="$CPPFLAGS $INCLUDES -I$abs_srcdir/main"'
        );
        // add for in-tree building
        file_put_contents(
            SOURCE_PATH . '/php-src/ext/memcache/php_memcache.h',
            <<<'EOF'
#ifndef PHP_MEMCACHE_H
#define PHP_MEMCACHE_H

extern zend_module_entry memcache_module_entry;
#define phpext_memcache_ptr &memcache_module_entry

#endif
EOF
        );
        return true;
    }
}
