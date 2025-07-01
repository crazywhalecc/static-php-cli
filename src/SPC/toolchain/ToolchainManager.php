<?php

declare(strict_types=1);

namespace SPC\toolchain;

use SPC\builder\linux\SystemUtil;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\util\GlobalEnvManager;
use SPC\util\SPCTarget;

class ToolchainManager
{
    public const array OS_DEFAULT_TOOLCHAIN = [
        'Linux' => ZigToolchain::class,
        'Windows' => MSVCToolchain::class,
        'Darwin' => ClangNativeToolchain::class,
        'BSD' => ClangNativeToolchain::class,
    ];

    public static function getToolchainClass(): string
    {
        $libc = getenv('SPC_LIBC');
        if ($libc && !getenv('SPC_TARGET')) {
            // TODO: @crazywhalecc this breaks tests
            // logger()->warning('SPC_LIBC is deprecated, please use SPC_TARGET instead.');
            return match ($libc) {
                'musl' => SystemUtil::isMuslDist() ? GccNativeToolchain::class : MuslToolchain::class,
                'glibc' => !SystemUtil::isMuslDist() ? GccNativeToolchain::class : throw new WrongUsageException('SPC_LIBC must be musl for musl dist.'),
                default => throw new WrongUsageException('Unsupported SPC_LIBC value: ' . $libc),
            };
        }

        return self::OS_DEFAULT_TOOLCHAIN[PHP_OS_FAMILY];
    }

    /**
     * @throws WrongUsageException
     */
    public static function initToolchain(): void
    {
        $toolchainClass = self::getToolchainClass();
        /* @var ToolchainInterface $toolchainClass */
        (new $toolchainClass())->initEnv();
        GlobalEnvManager::putenv("SPC_TOOLCHAIN={$toolchainClass}");
    }

    public static function afterInitToolchain(): void
    {
        if (!getenv('SPC_TOOLCHAIN')) {
            throw new WrongUsageException('SPC_TOOLCHAIN was not properly set. Please contact the developers.');
        }
        if (SPCTarget::getLibc() === 'musl' && !SPCTarget::isStatic() && !file_exists('/lib/ld-musl-x86_64.so.1')) {
            throw new RuntimeException('You are linking against musl libc dynamically, but musl libc is not installed. Please install it with `sudo dnf install musl-libc` or `sudo apt install musl`');
        }
        if (SPCTarget::getLibc() === 'glibc' && SystemUtil::isMuslDist()) {
            throw new RuntimeException('You are linking against glibc libc dynamically, which is only supported on glibc distros.');
        }
        $toolchain = getenv('SPC_TOOLCHAIN');
        /* @var ToolchainInterface $toolchain */
        (new $toolchain())->afterInit();
    }
}
