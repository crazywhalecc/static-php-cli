<?php

declare(strict_types=1);

namespace StaticPHP\Runtime;

use StaticPHP\Toolchain\ZigToolchain;
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
        if ($target = self::target()) {
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
            $target = self::target();
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
        $target = self::target();
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
        $target = self::target();
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

    /**
     * Returns the canonical target triple (arch-os-abi) for per-target build
     * artifacts. Always returns a non-null triple, falling back to a host-derived
     * triple when SPC_TARGET is unset or names 'native'.
     * Strips libc version suffix (-gnu.2.17 → -gnu) and trailing flags (' -dynamic').
     */
    public static function getCanonicalTriple(): string
    {
        $target = self::target();
        if ($target !== '' && !str_contains($target, 'native')) {
            $cleaned = (string) preg_replace('/(-gnu|-musl)\.[\d.]+/', '$1', $target);
            $cleaned = preg_split('/\s+/', trim($cleaned))[0] ?? '';
            if ($cleaned !== '') {
                return $cleaned;
            }
        }
        $arch = self::getTargetArch();
        return match (self::getTargetOS()) {
            'Linux' => $arch . '-linux-' . (self::getLibc() === 'musl' ? 'musl' : 'gnu'),
            'Darwin' => $arch . '-macos-none',
            'Windows' => $arch . '-windows-gnu',
            default => $arch . '-unknown-unknown',
        };
    }

    /**
     * Returns a GNU host triple for autoconf --host= when SPC_TARGET names an
     * architecture different from the build host (true cross-compile).
     * Returns null for same-arch builds.
     * Strips libc version suffix (-gnu.2.17 → -gnu) and trailing flags (e.g. ' -dynamic').
     */
    public static function getAutoconfHostTriple(): ?string
    {
        $target = self::target();
        if ($target === '' || str_contains($target, 'native')) {
            return null;
        }
        $cleaned = preg_split('/\s+/', trim((string) preg_replace('/(-gnu|-musl)\.[\d.]+/', '$1', $target)))[0];
        if ($cleaned === '') {
            return null;
        }
        // Only emit --host for true cross-arch builds; same-arch (incl. cross-libc) lets autoconf detect.
        $target_arch_token = explode('-', $cleaned)[0];
        $arch_aliases = [
            'x86_64' => ['x86_64', 'amd64'],
            'aarch64' => ['aarch64', 'arm64'],
            'arm' => ['arm', 'armv6', 'armv7', 'armhf', 'armel'],
            'i386' => ['i386', 'i486', 'i586', 'i686'],
        ];
        $host_arch = GNU_ARCH;
        if (array_any($arch_aliases, fn ($aliases) => in_array($target_arch_token, $aliases, true) && in_array($host_arch, $aliases, true))) {
            return null;
        }
        return $cleaned;
    }

    /** native toolchains ignore SPC_TARGET */
    private static function target(): string
    {
        $tc = (string) getenv('SPC_TOOLCHAIN');
        if ($tc !== '' && $tc !== ZigToolchain::class) {
            return '';
        }
        return (string) getenv('SPC_TARGET');
    }
}
