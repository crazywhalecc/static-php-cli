<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('ardillo')]
class ardillo extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        if (!is_dir(SOURCE_PATH . '/php-src/ext/ardillo')) {
            FileSystem::copyDir(SOURCE_PATH . '/ardillo', SOURCE_PATH . '/php-src/ext/ardillo');
        }
        FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/ext/ardillo/config.m4',
            'ARDILLO_CFLAGS="-Wl,--verbose -I$ARDILLO_DIR/libui-ng"',
            'ARDILLO_CFLAGS="-Wl,--verbose -I$ARDILLO_DIR/../../../../buildroot/include"'
        );
        FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/ext/ardillo/config.m4',
            'UI_LIB="-L$CURRENT_DIR/libui-ng/build/meson-out -Wl,-Bstatic -lui"',
            'UI_LIB="-L$CURRENT_DIR/../../buildroot/lib -Wl,-Bstatic -lui"'
        );
        return true;
    }
}
