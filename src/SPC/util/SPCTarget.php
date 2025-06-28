<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\WrongUsageException;
use SPC\util\toolchain\ClangNativeToolchain;
use SPC\util\toolchain\GccNativeToolchain;
use SPC\util\toolchain\MSVCToolchain;
use SPC\util\toolchain\MuslToolchain;
use SPC\util\toolchain\ToolchainInterface;
use SPC\util\toolchain\ZigToolchain;

/**
 * SPC build target constants and toolchain initialization.
 * format: {target_name}[-{libc_subtype}]
 */
class SPCTarget
{
    public const MUSL = 'musl';

    public const MUSL_STATIC = 'musl-static';

    public const GLIBC = 'glibc';

    public const MACHO = 'macho';

    public const MSVC_STATIC = 'msvc-static';

    public const TOOLCHAIN_LIST = [
        'musl' => MuslToolchain::class,
        'gcc-native' => GccNativeToolchain::class,
        'clang-native' => ClangNativeToolchain::class,
        'msvc' => MSVCToolchain::class,
        'zig' => ZigToolchain::class,
    ];

    public static function isTarget(string $target): bool
    {
        $env = getenv('SPC_TARGET');
        if ($env === false) {
            return false;
        }
        $env = strtolower($env);
        return $env === $target;
    }

    public static function isStaticTarget(): bool
    {
        $env = getenv('SPC_TARGET');
        if ($env === false) {
            return false;
        }
        $env = strtolower($env);
        return str_ends_with($env, '-static') || $env === self::MUSL_STATIC;
    }

    public static function initTargetForToolchain(string $toolchain): void
    {
        $target = getenv('SPC_TARGET');
        $toolchain = strtolower($toolchain);
        if (isset(self::TOOLCHAIN_LIST[$toolchain])) {
            $toolchainClass = self::TOOLCHAIN_LIST[$toolchain];
            /* @var ToolchainInterface $toolchainClass */
            (new $toolchainClass())->initEnv($target);
        }
        GlobalEnvManager::putenv("SPC_TOOLCHAIN={$toolchain}");
    }

    public static function afterInitTargetForToolchain()
    {
        if (!getenv('SPC_TOOLCHAIN')) {
            throw new WrongUsageException('SPC_TOOLCHAIN not set');
        }
        $toolchain = getenv('SPC_TOOLCHAIN');
        if (!isset(self::TOOLCHAIN_LIST[$toolchain])) {
            throw new WrongUsageException("Unknown toolchain: {$toolchain}");
        }
        $toolchainClass = self::TOOLCHAIN_LIST[$toolchain];
        (new $toolchainClass())->afterInit(getenv('SPC_TARGET'));
    }
}
