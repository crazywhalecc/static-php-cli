<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('imagick')]
class imagick extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        return '--with-imagick=' . ($shared ? 'shared,' : '') . BUILD_ROOT_PATH . ' ac_cv_func_omp_pause_resource_all=no';
    }

    protected function getStaticAndSharedLibs(): array
    {
        // on centos 7, it will use the symbol _ZTINSt6thread6_StateE, which is not defined in system libstdc++.so.6
        [$static, $shared] = parent::getStaticAndSharedLibs();
        if (str_contains(getenv('CC'), 'devtoolset-10')) {
            $static .= ' -lstdc++';
            $shared = str_replace('-lstdc++', '', $shared);
        }
        return [$static, $shared];
    }
}
