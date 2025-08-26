<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\builder\linux\SystemUtil;
use SPC\toolchain\ClangNativeToolchain;
use SPC\toolchain\GccNativeToolchain;
use SPC\toolchain\MuslToolchain;
use SPC\toolchain\ToolchainManager;

/**
 * SPC build target constants and toolchain initialization.
 * format: {target_name}[-{libc_subtype}]
 */
class SPCTarget
{
    public const array LIBC_LIST = ['musl', 'glibc'];

    /**
     * Returns whether we link the C runtime in statically.
     */
    public static function isStatic(): bool
    {
        if (ToolchainManager::getToolchainClass() === MuslToolchain::class) {
            return true;
        }
        if (ToolchainManager::getToolchainClass() === GccNativeToolchain::class) {
            return PHP_OS_FAMILY === 'Linux' && SystemUtil::isMuslDist();
        }
        if (ToolchainManager::getToolchainClass() === ClangNativeToolchain::class) {
            return PHP_OS_FAMILY === 'Linux' && SystemUtil::isMuslDist();
        }
        // if SPC_LIBC is set, it means the target is static, remove it when 3.0 is released
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
            if (str_contains($target, '-musl')) {
                return true;
            }
            if (PHP_OS_FAMILY === 'Linux') {
                return SystemUtil::isMuslDist();
            }
            return true;
        }
        if (getenv('SPC_LIBC') === 'musl') {
            return true;
        }
        return false;
    }

    /**
     * Returns the libc type if set, for other OS, it will always return null.
     */
    public static function getLibc(): ?string
    {
        if ($target = getenv('SPC_TARGET')) {
            if (str_contains($target, '-gnu')) {
                return 'glibc';
            }
            if (str_contains($target, '-musl')) {
                return 'musl';
            }
            if (PHP_OS_FAMILY === 'Linux') {
                return SystemUtil::isMuslDist() ? 'musl' : 'glibc';
            }
        }
        $libc = getenv('SPC_LIBC');
        if ($libc !== false) {
            return $libc;
        }
        if (PHP_OS_FAMILY === 'Linux') {
            return SystemUtil::isMuslDist() ? 'musl' : 'glibc';
        }
        return null;
    }

    public static function getRuntimeLibs(): string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            return self::getLibc() === 'musl' ? '-ldl -lpthread -lm' : '-ldl -lrt -lpthread -lm -lresolv -lutil';
        }
        if (PHP_OS_FAMILY === 'Darwin') {
            return '-lresolv';
        }
        return '';
    }

    /**
     * Returns the libc version if set, for other OS, it will always return null.
     */
    public static function getLibcVersion(): ?string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $target = (string) getenv('SPC_TARGET');
            if (str_contains($target, '-gnu.2.')) {
                return preg_match('/-gnu\.(2\.\d+)/', $target, $matches) ? $matches[1] : null;
            }
            $libc = self::getLibc();
            return SystemUtil::getLibcVersionIfExists($libc);
        }
        return null;
    }

    /**
     * Returns the target OS family, e.g. Linux, Darwin, Windows, BSD.
     * Currently, we only support native building.
     *
     * @return 'BSD'|'Darwin'|'Linux'|'Windows'
     */
    public static function getTargetOS(): string
    {
        $target = (string) getenv('SPC_TARGET');
        return match (true) {
            str_contains($target, '-linux') => 'Linux',
            str_contains($target, '-macos') => 'Darwin',
            str_contains($target, '-windows') => 'Windows',
            str_contains($target, '-native') => PHP_OS_FAMILY,
            default => PHP_OS_FAMILY,
        };
    }
}
