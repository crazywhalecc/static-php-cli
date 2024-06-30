<?php

declare(strict_types=1);

namespace SPC\builder\macos;

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
        [$ret, $output] = shell()->execWithResult('sysctl -n hw.ncpu', false);
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
            'x86_64' => '--target=x86_64-apple-darwin',
            'arm64','aarch64' => '--target=arm64-apple-darwin',
            default => throw new WrongUsageException('unsupported arch: ' . $arch),
        };
    }
}
