<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\unix\executor\UnixCMakeExecutor;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait snappy
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->setCMakeBuildDir("{$this->source_dir}/cmake/build")
            ->addConfigureArgs(
                '-DSNAPPY_BUILD_TESTS=OFF',
                '-DSNAPPY_BUILD_BENCHMARKS=OFF',
            )
            ->build('../..');
    }
}
