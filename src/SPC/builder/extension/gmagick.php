<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('gmagick')]
class gmagick extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        $disable_omp = ' ac_cv_func_omp_pause_resource_all=no';
        return '--with-gmagick=' . ($shared ? 'shared,' : '') . BUILD_ROOT_PATH . $disable_omp;
    }
}
