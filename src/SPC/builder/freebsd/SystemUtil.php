<?php

declare(strict_types=1);

namespace SPC\builder\freebsd;

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
        [$ret, $output] = shell()->execWithResult('sysctl -n hw.ncpu');
        if ($ret !== 0) {
            throw new EnvironmentException(
                'Failed to get cpu count from FreeBSD sysctl',
                'Please ensure you are running this command on a FreeBSD system and have the sysctl command available.'
            );
        }

        return (int) $output[0];
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
            'amd64', 'x86_64' => '--target=x86_64-unknown-freebsd',
            'arm64','aarch64' => '--target=aarch-unknown-freebsd',
            default => throw new WrongUsageException('unsupported arch: ' . $arch),
        };
    }
}
