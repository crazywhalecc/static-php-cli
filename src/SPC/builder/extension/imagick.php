<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\linux\LinuxBuilder;
use SPC\util\CustomExt;

#[CustomExt('imagick')]
class imagick extends Extension
{
    public function patchBeforeMake(): bool
    {
        // imagick may call omp_pause_all which requires -lgomp
        $extra_libs = getenv('SPC_EXTRA_LIBS') ?: '';
        if ($this->builder instanceof LinuxBuilder) {
            $extra_libs .= (empty($extra_libs) ? '' : ' ') . '-lgomp ';
        }
        f_putenv('SPC_EXTRA_LIBS=' . $extra_libs);
        return true;
    }

    public function getUnixConfigureArg(): string
    {
        return '--with-imagick=' . BUILD_ROOT_PATH;
    }
}
