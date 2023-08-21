<?php

declare(strict_types=1);

namespace SPC\doctor\item;

use SPC\builder\linux\SystemUtil;
use SPC\doctor\AsCheckItem;
use SPC\doctor\AsFixItem;
use SPC\doctor\CheckResult;
use SPC\exception\RuntimeException;

class LinuxMuslCheck
{
    /** @noinspection PhpUnused */
    #[AsCheckItem('if musl-libc is installed', limit_os: 'Linux')]
    public function checkMusl(): ?CheckResult
    {
        $file = sprintf('/lib/ld-musl-%s.so.1', php_uname('m'));
        if (file_exists($file)) {
            return CheckResult::ok();
        }

        // non-exist, need to recognize distro
        $distro = SystemUtil::getOSRelease();
        return match ($distro['dist']) {
            'ubuntu', 'alpine', 'debian' => CheckResult::fail('musl-libc is not installed on your system', 'fix-musl', [$distro]),
            default => CheckResult::fail('musl-libc is not installed on your system'),
        };
    }

    /**
     * @throws RuntimeException
     * @noinspection PhpUnused
     */
    #[AsFixItem('fix-musl')]
    public function fixMusl(array $distro): bool
    {
        $install_cmd = match ($distro['dist']) {
            'ubuntu', 'debian' => 'apt-get install musl musl-tools -y',
            'alpine' => 'apk add musl musl-utils musl-dev',
            default => throw new RuntimeException('Current linux distro is not supported for auto-install musl packages'),
        };
        $prefix = '';
        if (get_current_user() !== 'root') {
            $prefix = 'sudo ';
            logger()->warning('Current user is not root, using sudo for running command');
        }
        try {
            shell(true)->exec($prefix . $install_cmd);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
