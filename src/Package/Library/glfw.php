<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\Exception\BuildFailureException;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Toolchain\Interface\ToolchainInterface;

#[Library('glfw')]
class glfw
{
    #[Validate]
    public function validate(ToolchainInterface $toolchain): void
    {
        if (SystemTarget::getTargetOS() === 'Linux') {
            if ($toolchain->isStatic()) {
                throw new ValidationException('glfw library does not support full-static linking on Linux, please build with dynamic target specified.');
            }
            // detect X11 dev packages
            $required_headers = [
                '/usr/include/X11',
                '/usr/include/X11/extensions/Xrandr.h',
                '/usr/include/X11/extensions/Xinerama.h',
                '/usr/include/X11/Xcursor/Xcursor.h',
            ];
            foreach ($required_headers as $header) {
                if (!file_exists($header)) {
                    throw new ValidationException("glfw requires X11 development headers. Missing: {$header}. Please confirm that your system has the necessary X11 packages installed.");
                }
            }
        }
    }

    #[BuildFor('Linux')]
    public function buildForLinux(LibraryPackage $lib): void
    {
        $x11_lib_find = [
            '/usr/lib/' . SystemTarget::getTargetArch() . '-linux-gnu/libX11.so',
            '/usr/lib64/libX11.so',
            '/usr/lib/libX11.so',
        ];
        $found = false;
        foreach ($x11_lib_find as $path) {
            if (file_exists($path)) {
                $found = $path;
                break;
            }
        }
        if (!$found) {
            throw new BuildFailureException('Cannot find X11 library files in standard locations. Please ensure that the X11 development libraries are installed.');
        }
        $base_path = pathinfo($found, PATHINFO_DIRNAME);
        UnixCMakeExecutor::create($lib)
            ->setBuildDir("{$lib->getSourceDir()}/vendor/glfw")
            ->setReset(false)
            ->addConfigureArgs(
                '-DGLFW_BUILD_EXAMPLES=OFF',
                '-DGLFW_BUILD_TESTS=OFF',
                '-DGLFW_BUILD_DOCS=OFF',
                '-DX11_X11_INCLUDE_PATH=/usr/include',
                '-DX11_Xrandr_INCLUDE_PATH=/usr/include/X11/extensions',
                '-DX11_Xinerama_INCLUDE_PATH=/usr/include/X11/extensions',
                '-DX11_Xkb_INCLUDE_PATH=/usr/include/X11',
                '-DX11_Xcursor_INCLUDE_PATH=/usr/include/X11/Xcursor',
                '-DX11_Xi_INCLUDE_PATH=/usr/include/X11/extensions',
                "-DX11_X11_LIB={$base_path}/libX11.so",
                "-DX11_Xrandr_LIB={$base_path}/libXrandr.so",
                "-DX11_Xinerama_LIB={$base_path}/libXinerama.so",
                "-DX11_Xcursor_LIB={$base_path}/libXcursor.so",
                "-DX11_Xi_LIB={$base_path}/libXi.so"
            )
            ->build('.');
        // patch pkgconf
        $lib->patchPkgconfPrefix(['glfw3.pc']);
    }

    #[BuildFor('Darwin')]
    public function buildForMac(LibraryPackage $lib): void
    {
        UnixCMakeExecutor::create($lib)
            ->setBuildDir("{$lib->getSourceDir()}/vendor/glfw")
            ->setReset(false)
            ->addConfigureArgs(
                '-DGLFW_BUILD_EXAMPLES=OFF',
                '-DGLFW_BUILD_TESTS=OFF',
                '-DGLFW_BUILD_DOCS=OFF',
            )
            ->build('.');
        // patch pkgconf
        $lib->patchPkgconfPrefix(['glfw3.pc']);
    }
}
