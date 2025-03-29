<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('imagick')]
class imagick extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        // destroy imagick build conf to avoid libgomp build error
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/imagick/config.m4', '#include <omp.h>', '#include <NEVER_INCLUDE_OMP_H>');
        return true;
    }

    public function getUnixConfigureArg(): string
    {
        return '--with-imagick=' . BUILD_ROOT_PATH;
    }
}
