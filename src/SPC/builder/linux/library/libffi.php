<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\util\executor\UnixAutoconfExecutor;

class libffi extends LinuxLibraryBase
{
    public const NAME = 'libffi';

    public function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->configure()->make();

        if (is_file(BUILD_ROOT_PATH . '/lib64/libffi.a')) {
            copy(BUILD_ROOT_PATH . '/lib64/libffi.a', BUILD_ROOT_PATH . '/lib/libffi.a');
            unlink(BUILD_ROOT_PATH . '/lib64/libffi.a');
        }
        $this->patchPkgconfPrefix(['libffi.pc']);
    }
}
