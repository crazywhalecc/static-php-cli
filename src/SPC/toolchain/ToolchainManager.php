<?php

declare(strict_types=1);

namespace SPC\toolchain;

use SPC\builder\linux\SystemUtil;
use SPC\exception\WrongUsageException;
use SPC\util\GlobalEnvManager;

class ToolchainManager
{
    public const array OS_DEFAULT_TOOLCHAIN = [
        'Linux' => MuslToolchain::class, // use musl toolchain by default, after zig pr merged, change this to ZigToolchain::class
        'Windows' => MSVCToolchain::class,
        'Darwin' => ClangNativeToolchain::class,
        'BSD' => ClangNativeToolchain::class,
    ];

    /**
     * @throws WrongUsageException
     */
    public static function initToolchain(): void
    {
        $libc = getenv('SPC_LIBC');
        if ($libc !== false) {
            // uncomment this when zig pr is merged
            // logger()->warning('SPC_LIBC is deprecated, please use SPC_TARGET instead.');
            $toolchain = match ($libc) {
                'musl' => SystemUtil::isMuslDist() ? GccNativeToolchain::class : MuslToolchain::class,
                'glibc' => !SystemUtil::isMuslDist() ? GccNativeToolchain::class : throw new WrongUsageException('SPC_TARGET must be musl-static or musl for musl dist.'),
                default => throw new WrongUsageException('Unsupported SPC_LIBC value: ' . $libc),
            };
        } else {
            $toolchain = self::OS_DEFAULT_TOOLCHAIN[PHP_OS_FAMILY];
        }
        $toolchainClass = $toolchain;
        /* @var ToolchainInterface $toolchainClass */
        (new $toolchainClass())->initEnv();
        GlobalEnvManager::putenv("SPC_TOOLCHAIN={$toolchain}");
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
