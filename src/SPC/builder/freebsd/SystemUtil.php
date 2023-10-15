<?php

declare(strict_types=1);

namespace SPC\builder\freebsd;

use SPC\builder\traits\UnixSystemUtilTrait;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;

class SystemUtil
{
    /** Unix System Util Compatible */
    use UnixSystemUtilTrait;

    /**
     * Get Logic CPU Count for macOS
     *
     * @throws RuntimeException
     */
    public static function getCpuCount(): int
    {
        [$ret, $output] = shell()->execWithResult('sysctl -n hw.ncpu');
        if ($ret !== 0) {
            throw new RuntimeException('Failed to get cpu count');
        }

        return (int) $output[0];
    }

    /**
     * Get Target Arch CFlags
     *
     * @param  string              $arch Arch Name
     * @return string              return Arch CFlags string
     * @throws WrongUsageException
     */
    public static function getArchCFlags(string $arch): string
    {
        return match ($arch) {
            'amd64', 'x86_64' => '--target=x86_64-unknown-freebsd',
            'arm64','aarch64' => '--target=aarch-unknown-freebsd',
            default => throw new WrongUsageException('unsupported arch: ' . $arch),
        };
    }
}
