<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('enchant')]
class enchant extends Extension
{
    public function getUnixConfigureArg(): string
    {
        $glibs = [
            '/Users/jerry/project/git-project/static-php-cli/buildroot/lib/libgio-2.0.a',
            '/Users/jerry/project/git-project/static-php-cli/buildroot/lib/libglib-2.0.a',
            '/Users/jerry/project/git-project/static-php-cli/buildroot/lib/libgmodule-2.0.a',
            '/Users/jerry/project/git-project/static-php-cli/buildroot/lib/libgobject-2.0.a',
            '/Users/jerry/project/git-project/static-php-cli/buildroot/lib/libgthread-2.0.a',
            '/Users/jerry/project/git-project/static-php-cli/buildroot/lib/libintl.a',
        ];
        $arg = '--with-enchant="' . BUILD_ROOT_PATH . '"';
        $arg .= ' ENCHANT2_CFLAGS=-I"' . BUILD_INCLUDE_PATH . '/enchant-2"';
        $arg .= ' ENCHANT2_LIBS="' . $this->getLibFilesString() . '"';
        $arg .= ' GLIB_CFLAGS=-I"' . BUILD_INCLUDE_PATH . '"';
        $arg .= ' GLIB_LIBS="' . implode(' ', $glibs) . '"';
        return $arg;
    }
}
