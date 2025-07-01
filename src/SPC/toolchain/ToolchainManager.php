<?php

declare(strict_types=1);

namespace SPC\toolchain;

use SPC\builder\linux\SystemUtil;
use SPC\exception\WrongUsageException;
use SPC\util\GlobalEnvManager;

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
            throw new WrongUsageException('SPC_TOOLCHAIN not set');
        }
        $toolchain = getenv('SPC_TOOLCHAIN');
        /* @var ToolchainInterface $toolchain */
        (new $toolchain())->afterInit();
    }
}
