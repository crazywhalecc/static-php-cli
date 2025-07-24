<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\toolchain\ToolchainManager;
use SPC\toolchain\ZigToolchain;
use SPC\util\executor\UnixCMakeExecutor;
use SPC\util\SPCTarget;

trait libjxl
{
    protected function build(): void
    {
        if (ToolchainManager::getToolchainClass() === ZigToolchain::class) {
            if (str_contains(getenv('SPC_TARGET'), '.2.')) {
                throw new \RuntimeException('Zig toolchain does not support libjxl with target version.');
            }
            putenv('CC=gcc');
            putenv('CXX=g++');
        }
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs(
                '-DJPEGXL_ENABLE_TOOLS=OFF',
                '-DJPEGXL_ENABLE_EXAMPLES=OFF',
                '-DJPEGXL_ENABLE_MANPAGES=OFF',
                '-DJPEGXL_ENABLE_BENCHMARK=OFF',
                '-DJPEGXL_ENABLE_PLUGINS=OFF',
                '-DJPEGXL_ENABLE_SJPOEG=ON',
                '-DJPEGXL_ENABLE_JNI=OFF',
                '-DJPEGXL_ENABLE_TRANSCODE_JPEG=ON',
                '-DJPEGXL_STATIC=' . (SPCTarget::isStatic() ? 'ON' : 'OFF'),
                '-DJPEGXL_FORCE_SYSTEM_BROTLI=ON',
                '-DBUILD_TESTING=OFF'
            )
            ->build();
        if (ToolchainManager::getToolchainClass() === ZigToolchain::class) {
            putenv('CC=zig-cc');
            putenv('CXX=zig-c++');
        }
    }
}
