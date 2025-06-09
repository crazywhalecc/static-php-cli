<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\executor\UnixCMakeExecutor;

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
