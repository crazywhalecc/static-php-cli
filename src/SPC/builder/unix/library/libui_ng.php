<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait libui_ng
{
    public function build(): void
    {
        shell()->cd(SOURCE_PATH . '/ardillo/libui-ng')
            ->exec('meson setup --default-library=static --prefix=' . BUILD_ROOT_PATH . ' build')
            ->exec('ninja -C build install');
    }
}
