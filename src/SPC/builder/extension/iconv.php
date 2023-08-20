<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('iconv')]
class iconv extends Extension
{
    public function patchBeforeConfigure(): bool
    {
        // macOS need to link iconv dynamically, we add it to extra-libs
        $extra_libs = $this->builder->getOption('extra-libs', '');
        if (!str_contains($extra_libs, '-liconv')) {
            $extra_libs .= ' -liconv';
        }
        $this->builder->setOption('extra-libs', $extra_libs);
        return true;
    }
}
