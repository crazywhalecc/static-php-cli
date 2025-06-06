<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('xhprof')]
class xhprof extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        if (!is_link(SOURCE_PATH . '/php-src/ext/xhprof')) {
            if (PHP_OS_FAMILY === 'Windows') {
                f_passthru('cd ' . SOURCE_PATH . '/php-src/ext && mklink /D xhprof xhprof-src\extension');
            } else {
                f_passthru('cd ' . SOURCE_PATH . '/php-src/ext && ln -s xhprof-src/extension xhprof');
            }

            // patch config.m4
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/ext/xhprof/config.m4',
                'if test -f $phpincludedir/ext/pcre/php_pcre.h; then',
                'if test -f $abs_srcdir/ext/pcre/php_pcre.h; then'
            );
            return true;
        }
        return false;
    }
}
