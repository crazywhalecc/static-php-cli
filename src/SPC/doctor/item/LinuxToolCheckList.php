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
        'xz',
    ];

    public const TOOLS_RHEL = [
        'perl', 'make', 'bison', 'flex',
        'git', 'autoconf', 'automake',
        'tar', 'unzip', 'gzip', 'gcc',
        'bzip2', 'cmake', 'patch',
        'xz',
        'wget', // to get musl
    ];

    // todo: require those
    public const PAM_TOOLS_DEBIAN = [
        'autoconf', 'automake', 'autopoint',
        'bison', 'bzip2', 'docbook5-xml',
        'docbook-xsl-ns', 'flex', 'gettext',
        'libaudit-dev', 'libdb-dev', 'libfl-dev',
        'libselinux1-dev', 'libssl-dev', 'libtool',
        'libxml2-utils', 'make', 'pkg-config',
        'sed', 'w3m', 'xsltproc', 'xz-utils',
    ];

    // todo: require those
    public const PAM_TOOLS_RHEL = [
        'autoconf', 'automake', 'bison',
        'bzip2', 'flex', 'make', 'gettext',
        'pkg-config', 'sed', 'w3m', 'xz',
        'libdb-devel', 'libselinux-devel',
        'openssl-devel', 'libtool', 'libxml2',
        'docbook-xsl-ns', 'libxslt',
    ];

    /** @noinspection PhpUnused */
    #[AsCheckItem('if necessary tools are installed', limit_os: 'Linux', level: 999)]
    public function checkCliTools(): ?CheckResult
    {
        $distro = SystemUtil::getOSRelease();

        $required = match ($distro['dist']) {
            'alpine' => self::TOOLS_ALPINE,
            'almalinux' => self::TOOLS_RHEL,
            'rhel' => self::TOOLS_RHEL,
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
                'ubuntu',
                'alpine',
                'rhel',
                'almalinux',
                'debian' => CheckResult::fail(implode(', ', $missing) . ' not installed on your system', 'install-linux-tools', [$distro, $missing]),
                default => CheckResult::fail(implode(', ', $missing) . ' not installed on your system'),
            };
        }
        return CheckResult::ok();
    }

    /** @noinspection PhpUnused */
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

    /**
     * @throws RuntimeException
     * @noinspection PhpUnused
     */
    #[AsFixItem('install-linux-tools')]
    public function fixBuildTools(array $distro, array $missing): bool
    {
        $install_cmd = match ($distro['dist']) {
            'ubuntu', 'debian' => 'apt-get install -y',
            'alpine' => 'apk add',
            'rhel' => 'dnf install -y',
            'almalinux' => 'dnf install -y',
            default => throw new RuntimeException('Current linux distro does not have an auto-install script for musl packages yet.'),
        };
        $prefix = '';
        if (get_current_user() !== 'root') {
            $prefix = 'sudo ';
            logger()->warning('Current user is not root, using sudo for running command');
        }
        try {
            $is_rhel = in_array($distro['dist'], ['rhel', 'almalinux']);
            $to_install = $is_rhel ? $missing : str_replace('xz', 'xz-utils', $missing);
            shell(true)->exec($prefix . $install_cmd . ' ' . implode(' ', $to_install));
        } catch (RuntimeException) {
            return false;
        }
        return true;
    }
}
