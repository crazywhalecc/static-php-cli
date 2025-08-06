<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\util\executor\UnixCMakeExecutor;

class glfw extends MacOSLibraryBase
{
    public const NAME = 'glfw';

    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->setBuildDir("{$this->source_dir}/vendor/glfw")
            ->setReset(false)
            ->addConfigureArgs(
                '-DGLFW_BUILD_EXAMPLES=OFF',
                '-DGLFW_BUILD_TESTS=OFF',
            )
            ->build('.');
        // patch pkgconf
        $this->patchPkgconfPrefix(['glfw3.pc']);
    }
}
