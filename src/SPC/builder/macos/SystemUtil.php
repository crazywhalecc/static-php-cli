<?php

declare(strict_types=1);

namespace SPC\builder\macos;

use SPC\builder\traits\UnixSystemUtilTrait;
use SPC\exception\EnvironmentException;
use SPC\exception\WrongUsageException;

class SystemUtil
{
    /** Unix System Util Compatible */
    use UnixSystemUtilTrait;

    /**
     * Get Logic CPU Count for macOS
     */
    public static function getCpuCount(): int
    {
        $cpu = exec('sysctl -n hw.ncpu', $output, $ret);
        if ($ret !== 0) {
            throw new EnvironmentException(
                'Failed to get cpu count from macOS sysctl',
                'Please ensure you are running this command on a macOS system and have the sysctl command available.'
            );
        }

        return (int) $cpu;
    }

    /**
     * Get Target Arch CFlags
     *
     * @param  string $arch Arch Name
     * @return string return Arch CFlags string
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
