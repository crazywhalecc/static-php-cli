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
        if (getenv('SPC_LIBC') === 'glibc' && str_contains(getenv('CC'), 'devtoolset-10')) {
            return false;
        }
        // imagick with calls omp_pause_all which requires -lgomp, on non-musl we build imagick without openmp
        $extra_libs = trim(getenv('SPC_EXTRA_LIBS') . ' -lgomp');
        f_putenv('SPC_EXTRA_LIBS=' . $extra_libs);
        return true;
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        $disable_omp = getenv('SPC_LIBC') === 'musl' ? '' : ' ac_cv_func_omp_pause_resource_all=no';
        return '--with-imagick=' . ($shared ? 'shared,' : '') . BUILD_ROOT_PATH . $disable_omp;
    }
}
