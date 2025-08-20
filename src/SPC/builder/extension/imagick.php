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
        $disable_omp = ' ac_cv_func_omp_pause_resource_all=no';
        return '--with-imagick=' . ($shared ? 'shared,' : '') . BUILD_ROOT_PATH . $disable_omp;
    }

    protected function splitLibsIntoStaticAndShared(string $allLibs): array
    {
        [$static, $shared] = parent::splitLibsIntoStaticAndShared($allLibs);
        if (str_contains(getenv('PATH'), 'rh/devtoolset') || str_contains(getenv('PATH'), 'rh/gcc-toolset')) {
            $static .= ' -l:libstdc++.a';
            $shared = str_replace('-lstdc++', '', $shared);
        }
        return [clean_spaces($static), clean_spaces($shared)];
    }
}
