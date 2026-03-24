<?php

declare(strict_types=1);

namespace StaticPHP\Runtime;

use StaticPHP\Util\System\LinuxUtil;

/**
 * Originally from SPCTarget, used to offer some build-time information about the target.
 */
class SystemTarget
{
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
                return LinuxUtil::isMuslDist() ? 'musl' : 'glibc';
            }
        }
        $libc = getenv('SPC_LIBC');
        if ($libc !== false) {
            return $libc;
        }
        if (PHP_OS_FAMILY === 'Linux') {
            return LinuxUtil::isMuslDist() ? 'musl' : 'glibc';
        }
        return null;
    }

    /**
     * Get system runtime libraries linker flags.
     */
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
            return LinuxUtil::getLibcVersionIfExists($libc);
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

    /**
     * Returns the target architecture, e.g. x86_64, aarch64.
     * Currently, we only support 'x86_64' and 'aarch64' and both can only be built natively.
     */
    public static function getTargetArch(): string
    {
        $target = (string) getenv('SPC_TARGET');
        return match (true) {
            str_contains($target, 'x86_64') || str_contains($target, 'amd64') => 'x86_64',
            str_contains($target, 'aarch64') || str_contains($target, 'arm64') => 'aarch64',
            // str_contains($target, 'armv7') || str_contains($target, 'armhf') => 'armv7',
            // str_contains($target, 'armv6') || str_contains($target, 'armel') => 'armv6',
            // str_contains($target, 'i386') || str_contains($target, 'i686') => 'i386',
            default => GNU_ARCH,
        };
    }

    /**
     * Get the current platform string in the format of {os}-{arch}, e.g. linux-x86_64.
     */
    public static function getCurrentPlatformString(): string
    {
        $os = match (self::getTargetOS()) {
            'Darwin' => 'macos',
            'Linux' => 'linux',
            'Windows' => 'windows',
            default => 'unknown',
        };
        $arch = self::getTargetArch();
        if (getenv('EMULATE_PLATFORM') !== false) {
            return getenv('EMULATE_PLATFORM');
        }
        return "{$os}-{$arch}";
    }

    /**
     * Check if the target OS is a Unix-like system.
     */
    public static function isUnix(): bool
    {
        return in_array(self::getTargetOS(), ['Linux', 'Darwin', 'BSD']);
    }
}
