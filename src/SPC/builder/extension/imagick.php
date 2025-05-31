<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('imagick')]
class imagick extends Extension
{
    public function patchBeforeMake(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return false;
        }
        // imagemagick with --enable-openmp calls omp_pause_all which requires -lgomp on Linux
        $extra_libs = trim(getenv('SPC_EXTRA_LIBS') . ' -lgomp');
        f_putenv('SPC_EXTRA_LIBS=' . $extra_libs);
        return true;
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        return '--with-imagick=' . BUILD_ROOT_PATH ;
    }
}
