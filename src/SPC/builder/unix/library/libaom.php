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
        $cc = getenv('CC');
        $cxx = getenv('CXX');
        if (str_contains($cc, 'zig') && getenv('SPC_LIBC') === 'musl') {
            putenv('CC=' . $cc . ' -D_POSIX_SOURCE');
            putenv('CXX=' . $cxx . ' -D_POSIX_SOURCE');
        }
        UnixCMakeExecutor::create($this)
            ->setBuildDir("{$this->source_dir}/builddir")
            ->addConfigureArgs('-DAOM_TARGET_CPU=generic')
            ->build();
        if (str_contains($cc, 'zig') && getenv('SPC_LIBC') === 'musl') {
            putenv('CC=' . $cc);
            putenv('CXX=' . $cxx);
        }
        $this->patchPkgconfPrefix(['aom.pc']);
    }
}
