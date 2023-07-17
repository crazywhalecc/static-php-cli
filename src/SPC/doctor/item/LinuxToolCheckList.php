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
        'g++', 'patch',
    ];

    public const TOOLS_DEBIAN = [
        'make', 'bison', 'flex',
        'git', 'autoconf', 'automake',
        'tar', 'unzip', 'gzip',
        'bzip2', 'cmake', 'patch',
    ];

    #[AsCheckItem('if necessary tools are installed', limit_os: 'Linux')]
    public function checkCliTools(): ?CheckResult
    {
        $distro = SystemUtil::getOSRelease();

        $required = match ($distro['dist']) {
            'alpine' => self::TOOLS_ALPINE,
            default => self::TOOLS_DEBIAN,
        };
        $missing = [];
        foreach ($required as $cmd) {
            if ($this->findCommand($cmd) === null) {
                $missing[] = $cmd;
            }
        }
        if (!empty($missing)) {
            return match ($distro['dist']) {
                'ubuntu', 'alpine', 'debian' => CheckResult::fail(implode(', ', $missing) . ' not installed on your system', 'install-linux-tools', [$distro, $missing]),
                default => CheckResult::fail(implode(', ', $missing) . ' not installed on your system'),
            };
        }
        return CheckResult::ok();
    }

    #[AsCheckItem('if necessary packages are installed', limit_os: 'Linux')]
    public function checkSystemOSPackages(): ?CheckResult
    {
        $distro = SystemUtil::getOSRelease();
        if ($distro['dist'] === 'alpine') {
            // check linux-headers installation
            if (!file_exists('/usr/include/linux/mman.h')) {
                return CheckResult::fail('linux-headers not installed on your system', 'install-linux-tools', [$distro, ['linux-headers']]);
            }
        }
        return CheckResult::ok();
    }

    #[AsFixItem('install-linux-tools')]
    public function fixBuildTools(array $distro, array $missing): bool
    {
        $install_cmd = match ($distro['dist']) {
            'ubuntu', 'debian' => 'apt install -y',
            'alpine' => 'apk add',
            default => throw new RuntimeException('Current linux distro is not supported for auto-install musl packages'),
        };
        $prefix = '';
        if (get_current_user() !== 'root') {
            $prefix = 'sudo ';
            logger()->warning('Current user is not root, using sudo for running command');
        }
        try {
            shell(true)->exec($prefix . $install_cmd . ' ' . implode(' ', $missing));
        } catch (RuntimeException) {
            return false;
        }
        return true;
    }
}
