<?php

declare(strict_types=1);

namespace SPC\doctor\item;

use SPC\builder\linux\SystemUtil;
use SPC\builder\traits\UnixSystemUtilTrait;
use SPC\doctor\AsCheckItem;
use SPC\doctor\AsFixItem;
use SPC\doctor\CheckResult;
use SPC\exception\RuntimeException;

class LinuxToolCheckList
{
    use UnixSystemUtilTrait;

    public const TOOLS_ALPINE = [
        'make', 'bison', 'flex',
        'git', 'autoconf', 'automake',
        'tar', 'unzip', 'gzip',
        'bzip2', 'cmake', 'gcc',
        'g++', 'patch', 'binutils-gold',
        'libtoolize',
    ];

    public const TOOLS_DEBIAN = [
        'make', 'bison', 'flex',
        'git', 'autoconf', 'automake',
        'tar', 'unzip', 'gzip',
        'bzip2', 'cmake', 'patch',
        'xz', 'libtoolize',
    ];

    public const TOOLS_RHEL = [
        'perl', 'make', 'bison', 'flex',
        'git', 'autoconf', 'automake',
        'tar', 'unzip', 'gzip', 'gcc',
        'bzip2', 'cmake', 'patch',
        'xz',
    ];

    private const PROVIDED_COMMAND = [
        'binutils-gold' => 'ld.gold',
    ];

    /** @noinspection PhpUnused */
    #[AsCheckItem('if necessary tools are installed', limit_os: 'Linux', level: 999)]
    public function checkCliTools(): ?CheckResult
    {
        $distro = SystemUtil::getOSRelease();

        $required = match ($distro['dist']) {
            'alpine' => self::TOOLS_ALPINE,
            'redhat' => self::TOOLS_RHEL,
            default => self::TOOLS_DEBIAN,
        };
        $missing = [];
        foreach ($required as $package) {
            if ($this->findCommand(self::PROVIDED_COMMAND[$package] ?? $package) === null) {
                $missing[] = $package;
            }
        }
        if (!empty($missing)) {
            return match ($distro['dist']) {
                'ubuntu',
                'alpine',
                'redhat',
                'Deepin',
                'debian' => CheckResult::fail(implode(', ', $missing) . ' not installed on your system', 'install-linux-tools', [$distro, $missing]),
                default => CheckResult::fail(implode(', ', $missing) . ' not installed on your system'),
            };
        }
        return CheckResult::ok();
    }

    /** @noinspection PhpUnused */
    #[AsCheckItem('if necessary linux headers are installed', limit_os: 'Linux')]
    public function checkSystemOSPackages(): ?CheckResult
    {
        if (SystemUtil::isMuslDist()) {
            // check linux-headers installation
            if (!file_exists('/usr/include/linux/mman.h')) {
                return CheckResult::fail('linux-headers not installed on your system', 'install-linux-tools', [SystemUtil::getOSRelease(), ['linux-headers']]);
            }
        }
        return CheckResult::ok();
    }

    /**
     * @throws RuntimeException
     * @noinspection PhpUnused
     */
    #[AsFixItem('install-linux-tools')]
    public function fixBuildTools(array $distro, array $missing): bool
    {
        $install_cmd = match ($distro['dist']) {
            'ubuntu', 'debian', 'Deepin' => 'apt-get install -y',
            'alpine' => 'apk add',
            'redhat' => 'dnf install -y',
            default => throw new RuntimeException('Current linux distro does not have an auto-install script for musl packages yet.'),
        };
        $prefix = '';
        if (get_current_user() !== 'root') {
            $prefix = 'sudo ';
            logger()->warning('Current user is not root, using sudo for running command');
        }
        try {
            $is_debian = in_array($distro['dist'], ['debian', 'ubuntu', 'Deepin']);
            $to_install = $is_debian ? str_replace('xz', 'xz-utils', $missing) : $missing;
            // debian, alpine libtool -> libtoolize
            $to_install = str_replace('libtoolize', 'libtool', $to_install);
            shell(true)->exec($prefix . $install_cmd . ' ' . implode(' ', $to_install));
        } catch (RuntimeException) {
            return false;
        }
        return true;
    }
}
