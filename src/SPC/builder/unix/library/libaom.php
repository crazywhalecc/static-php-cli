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
        UnixCMakeExecutor::create($this)
            ->setBuildDir("{$this->source_dir}/builddir")
            ->addConfigureArgs('-DAOM_TARGET_CPU=generic')
            ->build();
        $this->patchPkgconfPrefix(['aom.pc']);
    }
}
