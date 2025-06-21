<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\store\FileSystem;

/**
 * a template library class for unix
 */
class imagemagick extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\imagemagick;

    public const NAME = 'imagemagick';

    public function patchPhpConfig(): bool
    {
        if (getenv('SPC_LIBC') !== 'glibc' || !str_contains(getenv('CC'), 'devtoolset-10')) {
            FileSystem::replaceFileRegex(BUILD_BIN_PATH . '/php-config', '/^libs="(.*)"$/m', 'libs="$1 -lgomp"');
            return true;
        }
        return false;
    }
}
