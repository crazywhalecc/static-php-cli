<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\unix\executor\UnixCMakeExecutor;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait tidy
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->setCMakeBuildDir("{$this->source_dir}/build-dir")
            ->addConfigureArgs('-DSUPPORT_CONSOLE_APP=OFF')
            ->build();
        $this->patchPkgconfPrefix(['tidy.pc']);
    }
}
