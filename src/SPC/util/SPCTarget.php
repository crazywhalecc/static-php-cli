<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\builder\linux\SystemUtil;
use SPC\exception\WrongUsageException;

/**
 * SPC build target constants and toolchain initialization.
 * format: {target_name}[-{libc_subtype}]
 */
class SPCTarget
{
    public const array LIBC_LIST = [
        'musl',
        'glibc',
    ];

    /**
     * Returns whether we link the C runtime in statically.
     */
    public static function isStatic(): bool
    {
        $libc = getenv('SPC_LIBC');
        // if SPC_LIBC is set, it means the target is static, remove it when 3.0 is released
        if ($libc === 'musl') {
            return true;
        }
        if ($target = getenv('SPC_TARGET')) {
            if (str_contains($target, '-macos') || str_contains($target, '-native') && PHP_OS_FAMILY === 'Darwin') {
                return false;
            }
            if (str_contains($target, '-gnu')) {
                return false;
            }
            if (str_contains($target, '-dynamic')) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Returns the libc type if set, for other OS, it will always return null.
     */
    public static function getLibc(): ?string
    {
        $libc = getenv('SPC_LIBC');
        if ($libc !== false) {
            return $libc;
        }
        $target = getenv('SPC_TARGET');
        if (str_contains($target, '-gnu')) {
            return 'glibc';
        }
        if (str_contains($target, '-musl')) {
            return 'musl';
        }
        if (str_contains($target, '-linux')) {
            return 'musl';
        }
        if (PHP_OS_FAMILY === 'Linux' && str_contains($target, '-native')) {
            return 'musl';
        }
        return null;
    }

    /**
     * Returns the libc version if set, for other OS, it will always return null.
     */
    public static function getLibcVersion(): ?string
    {
        $env = getenv('SPC_TARGET');
        $libc = getenv('SPC_LIBC');
        if ($libc !== false) {
            // legacy method: get a version from system
            return SystemUtil::getLibcVersionIfExists($libc);
        }
        // TODO: zig target parser

        return null;
    }

    /**
     * Returns the target OS family, e.g. Linux, Darwin, Windows, BSD.
     * Currently, we only support native building.
     *
     * @return 'BSD'|'Darwin'|'Linux'|'Windows'
     * @throws WrongUsageException
     */
    public static function getTargetOS(): string
    {
        $target = getenv('SPC_TARGET');
        if ($target === false) {
            return PHP_OS_FAMILY;
        }
        // TODO: zig target parser like below?
        return match (true) {
            str_contains($target, 'linux') => 'Linux',
            str_contains($target, 'macos') => 'Darwin',
            str_contains($target, 'windows') => 'Windows',
            default => throw new WrongUsageException('Cannot parse target.'),
        };
    }
}
