<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\executor\UnixCMakeExecutor;

trait libaom
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        if (getenv('SPC_LIBC') === 'musl' && str_contains(getenv('CC'), 'zig')) {
            f_putenv('COMPILER_EXTRA=-D_POSIX_SOURCE');
        }
        UnixCMakeExecutor::create($this)
            ->setBuildDir("{$this->source_dir}/builddir")
            ->addConfigureArgs('-DAOM_TARGET_CPU=generic')
            ->build();
        f_putenv('COMPILER_EXTRA');
        $this->patchPkgconfPrefix(['aom.pc']);
    }
}
