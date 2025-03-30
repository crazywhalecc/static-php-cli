<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\FileSystemException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('imagick')]
class imagick extends Extension
{
    /**
     * @throws FileSystemException
     */
    public function patchBeforeMake(): bool
    {
        // replace php_config.h HAVE_OMP_PAUSE_RESOURCE_ALL line to #define HAVE_OMP_PAUSE_RESOURCE_ALL 0
        FileSystem::replaceFileLineContainsString(SOURCE_PATH . '/php-src/main/php_config.h', 'HAVE_OMP_PAUSE_RESOURCE_ALL', '#define HAVE_OMP_PAUSE_RESOURCE_ALL 0');
        return true;
    }

    public function getUnixConfigureArg(): string
    {
        return '--with-imagick=' . BUILD_ROOT_PATH;
    }
}
