<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\executor\UnixAutoconfExecutor;

class libffi extends LinuxLibraryBase
{
    public const NAME = 'libffi';

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function build(): void
    {
        $arch = getenv('SPC_ARCH');
        UnixAutoconfExecutor::create($this)
            ->configure(
                "--host={$arch}-unknown-linux",
                "--target={$arch}-unknown-linux",
                "--libdir={$this->getLibDir()}"
            )
            ->make();

        if (is_file(BUILD_ROOT_PATH . '/lib64/libffi.a')) {
            copy(BUILD_ROOT_PATH . '/lib64/libffi.a', BUILD_ROOT_PATH . '/lib/libffi.a');
            unlink(BUILD_ROOT_PATH . '/lib64/libffi.a');
        }
        $this->patchPkgconfPrefix(['libffi.pc']);
    }
}
