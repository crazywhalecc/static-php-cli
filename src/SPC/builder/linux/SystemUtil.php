<?php

declare(strict_types=1);

namespace SPC\builder\linux;

use JetBrains\PhpStorm\ArrayShape;
use SPC\builder\traits\UnixSystemUtilTrait;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;

class SystemUtil
{
    use UnixSystemUtilTrait;

    #[ArrayShape(['dist' => 'mixed|string', 'ver' => 'mixed|string'])]
    public static function getOSRelease(): array
    {
        $ret = [
            'dist' => 'unknown',
            'ver' => 'unknown',
        ];
        switch (true) {
            case file_exists('/etc/os-release'):
                $lines = file('/etc/os-release');
                foreach ($lines as $line) {
                    if (preg_match('/^ID=(.*)$/', $line, $matches)) {
                        $ret['dist'] = $matches[1];
                    }
                    if (preg_match('/^VERSION_ID=(.*)$/', $line, $matches)) {
                        $ret['ver'] = $matches[1];
                    }
                }
                $ret['dist'] = trim($ret['dist'], '"\'');
                $ret['ver'] = trim($ret['ver'], '"\'');
                if (strcasecmp($ret['dist'], 'centos') === 0) {
                    $ret['dist'] = 'redhat';
                }
                break;
            case file_exists('/etc/centos-release'):
                $lines = file('/etc/centos-release');
                goto rh;
            case file_exists('/etc/redhat-release'):
                $lines = file('/etc/redhat-release');
                rh:
                foreach ($lines as $line) {
                    if (preg_match('/release\s+(\d+(\.\d+)*)/', $line, $matches)) {
                        $ret['dist'] = 'redhat';
                        $ret['ver'] = $matches[1];
                    }
                }
                break;
        }
        return $ret;
    }

    public static function getCpuCount(): int
    {
        $ncpu = 1;

        if (is_file('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $ncpu = count($matches[0]);
        }

        return $ncpu;
    }

    /**
     * @throws RuntimeException
     */
    public static function getCCType(string $cc): string
    {
        return match (true) {
            str_ends_with($cc, 'c++'), str_ends_with($cc, 'cc'), str_ends_with($cc, 'g++'), str_ends_with($cc, 'gcc') => 'gcc',
            $cc === 'clang++', $cc === 'clang', str_starts_with($cc, 'musl-clang') => 'clang',
            default => throw new RuntimeException("unknown cc type: {$cc}"),
        };
    }

    /**
     * @throws RuntimeException
     */
    public static function getArchCFlags(string $cc, string $arch): string
    {
        if (php_uname('m') === $arch) {
            return '';
        }
        return match (static::getCCType($cc)) {
            'clang' => match ($arch) {
                'x86_64' => '--target=x86_64-unknown-linux',
                'arm64', 'aarch64' => '--target=arm64-unknown-linux',
                default => throw new WrongUsageException('unsupported arch: ' . $arch),
            },
            'gcc' => '',
            default => throw new WrongUsageException('cc compiler ' . $cc . ' is not supported'),
        };
    }

    /**
     * @throws RuntimeException
     */
    public static function getTuneCFlags(string $arch): array
    {
        return match ($arch) {
            'x86_64' => [
                '-march=corei7',
                '-mtune=core-avx2',
            ],
            'arm64', 'aarch64' => [],
            default => throw new RuntimeException('unsupported arch: ' . $arch),
        };
    }

    public static function checkCCFlags(array $flags, string $cc): array
    {
        return array_filter($flags, fn ($flag) => static::checkCCFlag($flag, $cc));
    }

    public static function checkCCFlag(string $flag, string $cc): string
    {
        [$ret] = shell()->execWithResult("echo | {$cc} -E -x c - {$flag}");
        if ($ret != 0) {
            return '';
        }
        return $flag;
    }

    /**
     * @throws RuntimeException
     */
    public static function getCrossCompilePrefix(string $cc, string $arch): string
    {
        return match (static::getCCType($cc)) {
            // guessing clang toolchains
            'clang' => match ($arch) {
                'x86_64' => 'x86_64-linux-gnu-',
                'arm64', 'aarch64' => 'aarch64-linux-gnu-',
                default => throw new RuntimeException('unsupported arch: ' . $arch),
            },
            // remove gcc postfix
            'gcc' => str_replace('-cc', '', str_replace('-gcc', '', $cc)) . '-',
            default => throw new RuntimeException('unsupported cc'),
        };
    }

    public static function findStaticLib(string $name): ?array
    {
        $paths = getenv('LIBPATH');
        if (!$paths) {
            $paths = '/lib:/lib64:/usr/lib:/usr/lib64:/usr/local/lib:/usr/local/lib64:';
        }
        foreach (explode(':', $paths) as $path) {
            if (file_exists("{$path}/{$name}")) {
                return ["{$path}", "{$name}"];
            }
        }
        return null;
    }

    public static function findStaticLibs(array $names): ?array
    {
        $ret = [];
        foreach ($names as $name) {
            $path = static::findStaticLib($name);
            if (!$path) {
                logger()->warning("static library {$name} not found");
                return null;
            }
            $ret[] = $path;
        }
        return $ret;
    }

    public static function findHeader(string $name): ?array
    {
        $paths = getenv('INCLUDEPATH');
        if (!$paths) {
            $paths = '/include:/usr/include:/usr/local/include';
        }
        foreach (explode(':', $paths) as $path) {
            if (file_exists("{$path}/{$name}") || is_dir("{$path}/{$name}")) {
                return ["{$path}", "{$name}"];
            }
        }
        return null;
    }

    public static function findHeaders(array $names): ?array
    {
        $ret = [];
        foreach ($names as $name) {
            $path = static::findHeader($name);
            if (!$path) {
                logger()->warning("header {$name} not found");
                return null;
            }
            $ret[] = $path;
        }
        return $ret;
    }
}
