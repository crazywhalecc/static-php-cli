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
        if (getenv('SPC_LIBC') === 'glibc' && str_contains(getenv('CC'), 'devtoolset-10')) {
            return false;
        }
        // imagick with calls omp_pause_all, which requires openmp, on non-musl we build imagick without openmp
        $extra_libs = trim(getenv('SPC_EXTRA_LIBS') . ' -lgomp');
        f_putenv('SPC_EXTRA_LIBS=' . $extra_libs);
        return true;
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        $disable_omp = !(getenv('SPC_LIBC') === 'glibc' && str_contains(getenv('CC'), 'devtoolset-10')) ? '' : ' ac_cv_func_omp_pause_resource_all=no';
        return '--with-imagick=' . ($shared ? 'shared,' : '') . BUILD_ROOT_PATH . $disable_omp;
    }

    protected function getStaticAndSharedLibs(): array
    {
        // on centos 7, it will use the symbol _ZTINSt6thread6_StateE, which is not defined in system libstdc++.so.6
        [$static, $shared] = parent::getStaticAndSharedLibs();
        if (getenv('SPC_LIBC') === 'glibc' && str_contains(getenv('CC'), 'devtoolset-10')) {
            $static .= ' -lstdc++';
            $shared = str_replace('-lstdc++', '', $shared);
        }
        return [$static, $shared];
    }
}
