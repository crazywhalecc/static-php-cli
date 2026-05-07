<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageBuilder;

#[Extension('imagick')]
class imagick
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageBuilder $builder): string
    {
        $disable_omp = ' ac_cv_func_omp_pause_resource_all=no';
        return '--with-imagick=' . ($shared ? 'shared,' : '') . $builder->getBuildRootPath() . $disable_omp;
    }
}
