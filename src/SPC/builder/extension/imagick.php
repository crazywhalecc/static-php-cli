<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\linux\SystemUtil;
use SPC\util\CustomExt;

#[CustomExt('imagick')]
class imagick extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        $disable_omp = ' ac_cv_func_omp_pause_resource_all=no';
        return '--with-imagick=' . ($shared ? 'shared,' : '') . BUILD_ROOT_PATH . $disable_omp;
    }

    protected function getStaticAndSharedLibs(): array
    {
        [$static, $shared] = parent::getStaticAndSharedLibs();
        if (SystemUtil::getLibcVersionIfExists('glibc') && SystemUtil::getLibcVersionIfExists('glibc') <= '2.17') {
            $static .= ' -lstdc++';
            $shared = str_replace('-lstdc++', '', $shared);
        }
        return [$static, $shared];
    }
}
