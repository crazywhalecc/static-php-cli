<?php

declare(strict_types=1);

namespace SPC\builder\linux;

use SPC\builder\traits\UnixSystemUtilTrait;

class SystemUtil
{
    use UnixSystemUtilTrait;

    public static ?string $libc_version = null;

    /** @noinspection PhpMissingBreakStatementInspection */
    public static function getOSRelease(): array
    {
        $ret = [
            'dist' => 'unknown',
            'ver' => 'unknown',
        ];
        switch (true) {
            case file_exists('/etc/centos-release'):
                $lines = file('/etc/centos-release');
                $centos = true;
                goto rh;
            case file_exists('/etc/redhat-release'):
                $lines = file('/etc/redhat-release');
                $centos = false;
                rh:
                foreach ($lines as $line) {
                    if (preg_match('/release\s+(\d*(\.\d+)*)/', $line, $matches)) {
                        /* @phpstan-ignore-next-line */
                        $ret['dist'] = $centos ? 'centos' : 'redhat';
                        $ret['ver'] = $matches[1];
                    }
                }
                break;
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
        }
        return $ret;
    }

    public static function isMuslDist(): bool
    {
        return static::getOSRelease()['dist'] === 'alpine';
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

    /** @noinspection PhpUnused */
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

    /** @noinspection PhpUnused */
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

    /**
     * Get fully-supported linux distros.
     *
     * @return string[] List of supported Linux distro name for doctor
     */
    public static function getSupportedDistros(): array
    {
        return [
            // debian-like
            'debian', 'ubuntu', 'Deepin',
            // rhel-like
            'redhat',
            // centos
            'centos',
            // alpine
            'alpine',
            // arch
            'arch', 'manjaro',
        ];
    }

    /**
     * Get libc version string from ldd
     */
    public static function getLibcVersionIfExists(?string $libc = null): ?string
    {
        if (self::$libc_version !== null) {
            return self::$libc_version;
        }
        if ($libc === 'glibc') {
            $result = shell()->execWithResult('ldd --version', false);
            if ($result[0] !== 0) {
                return null;
            }
            // get first line
            $first_line = $result[1][0];
            // match ldd version: "ldd (some useless text) 2.17" match 2.17
            $pattern = '/ldd\s+\(.*?\)\s+(\d+\.\d+)/';
            if (preg_match($pattern, $first_line, $matches)) {
                self::$libc_version = $matches[1];
                return self::$libc_version;
            }
            return null;
        }
        if ($libc === 'musl') {
            if (self::isMuslDist()) {
                $result = shell()->execWithResult('ldd 2>&1', false);
            } elseif (is_file('/usr/local/musl/lib/libc.so')) {
                $result = shell()->execWithResult('/usr/local/musl/lib/libc.so 2>&1', false);
            } else {
                $arch = php_uname('m');
                $result = shell()->execWithResult("/lib/ld-musl-{$arch}.so.1 2>&1", false);
            }
            // Match Version * line
            // match ldd version: "Version 1.2.3" match 1.2.3
            $pattern = '/Version\s+(\d+\.\d+\.\d+)/';
            if (preg_match($pattern, $result[1][1] ?? '', $matches)) {
                self::$libc_version = $matches[1];
                return self::$libc_version;
            }
        }
        return null;
    }
}
