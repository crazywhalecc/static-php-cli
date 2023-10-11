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
        if (!str_contains($extra_libs, 'iconv')) {
            $extra_libs .= ' ' . BUILD_LIB_PATH . '/libiconv.a';
        }
        $this->builder->setOption('extra-libs', $extra_libs);
        return true;
    }
}
