<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Toolchain\ZigToolchain;

#[Library('libjxl')]
class libjxl extends LibraryPackage
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(ToolchainInterface $toolchain): void
    {
        $cmake = UnixCMakeExecutor::create($this)
            ->addConfigureArgs(
                '-DJPEGXL_ENABLE_TOOLS=OFF',
                '-DJPEGXL_ENABLE_EXAMPLES=OFF',
                '-DJPEGXL_ENABLE_MANPAGES=OFF',
                '-DJPEGXL_ENABLE_BENCHMARK=OFF',
                '-DJPEGXL_ENABLE_PLUGINS=OFF',
                '-DJPEGXL_ENABLE_SJPEG=ON',
                '-DJPEGXL_ENABLE_JNI=OFF',
                '-DJPEGXL_ENABLE_TRANSCODE_JPEG=ON',
                '-DJPEGXL_STATIC=' . ($toolchain->isStatic() ? 'ON' : 'OFF'),
                '-DJPEGXL_FORCE_SYSTEM_BROTLI=ON',
                '-DBUILD_TESTING=OFF'
            );

        if ($toolchain instanceof ZigToolchain) {
            $cflags = getenv('SPC_DEFAULT_C_FLAGS') ?: getenv('CFLAGS') ?: '';
            $has_avx512 = str_contains($cflags, '-mavx512') || str_contains($cflags, '-march=x86-64-v4');
            if (!$has_avx512) {
                $cmake->addConfigureArgs(
                    '-DCXX_MAVX512F_SUPPORTED:BOOL=FALSE',
                    '-DCXX_MAVX512DQ_SUPPORTED:BOOL=FALSE',
                    '-DCXX_MAVX512CD_SUPPORTED:BOOL=FALSE',
                    '-DCXX_MAVX512BW_SUPPORTED:BOOL=FALSE',
                    '-DCXX_MAVX512VL_SUPPORTED:BOOL=FALSE'
                );
            }
        }

        $cmake->build();
    }
}
