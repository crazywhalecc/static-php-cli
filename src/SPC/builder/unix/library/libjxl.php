<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixCMakeExecutor;
use SPC\util\SPCTarget;

trait libjxl
{
    public function patchBeforeBuild(): bool
    {
        $depsContent = file_get_contents($this->source_dir . '/deps.sh');
        if (str_contains($depsContent, '# return 0')) {
            return false;
        }
        FileSystem::replaceFileStr(
            $this->source_dir . '/deps.sh',
            'return 0',
            '# return 0',
        );
        shell()->cd($this->source_dir)
            ->exec('./deps.sh');
        return true;
    }

    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs('-DJPEGXL_ENABLE_TOOLS=OFF')
            ->addConfigureArgs('-DJPEGXL_ENABLE_EXAMPLES=OFF')
            ->addConfigureArgs('-DJPEGXL_ENABLE_MANPAGES=OFF')
            ->addConfigureArgs('-DJPEGXL_ENABLE_BENCHMARK=OFF')
            ->addConfigureArgs('-DJPEGXL_ENABLE_PLUGINS=OFF')
            ->addConfigureArgs('-DJPEGXL_ENABLE_SJPEG=OFF')
            ->addConfigureArgs('-DJPEGXL_STATIC=' . (SPCTarget::isStatic() ? 'ON' : 'OFF'))
            ->addConfigureArgs('-DBUILD_TESTING=OFF')
            ->build();
    }
}
