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
        // imagick may call omp_pause_all which requires -lgomp
        $extra_libs = getenv('SPC_EXTRA_LIBS') ?: '';
        if (getenv('SPC_LIBC') === 'musl') {
            $extra_libs = trim($extra_libs . ' -lgomp');
        }
        if (getenv('SPC_LIBC') === 'glibc') {
            $extra_libs = trim($extra_libs . ' -l:libgomp.a');
        }
        f_putenv('SPC_EXTRA_LIBS=' . $extra_libs);
        return true;
    }

    public function getUnixConfigureArg(): string
    {
        return '--with-imagick=' . BUILD_ROOT_PATH;
    }
}
