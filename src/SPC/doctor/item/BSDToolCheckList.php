<?php

declare(strict_types=1);

namespace SPC\doctor\item;

use SPC\builder\traits\UnixSystemUtilTrait;
use SPC\doctor\AsCheckItem;
use SPC\doctor\AsFixItem;
use SPC\doctor\CheckResult;
use SPC\exception\RuntimeException;

class BSDToolCheckList
{
    use UnixSystemUtilTrait;

    /** @var string[] FreeBSD */
    public const REQUIRED_COMMANDS = [
        'curl',
        'make',
        'bison',
        'flex',
        'pkg-config',
        'git',
        'autoconf',
        'automake',
        'tar',
        'unzip',
        'xz',
        'gzip',
        'bzip2',
        'cmake',
    ];

    #[AsCheckItem('if necessary tools are installed', limit_os: 'BSD')]
    public function checkCliTools(): ?CheckResult
    {
        $missing = [];
        foreach (self::REQUIRED_COMMANDS as $cmd) {
            if ($this->findCommand($cmd) === null) {
                $missing[] = $cmd;
            }
        }
        if (!empty($missing)) {
            return CheckResult::fail('missing system commands: ' . implode(', ', $missing), 'build-tools-bsd', [$missing]);
        }
        return CheckResult::ok();
    }

    #[AsFixItem('build-tools-bsd')]
    public function fixBuildTools(array $missing): bool
    {
        if (get_current_user() !== 'root') {
            $prefix = 'sudo ';
            logger()->warning('Current user is not root, using sudo for running command');
        } else {
            $prefix = '';
        }
        try {
            shell(true)->exec("ASSUME_ALWAYS_YES=yes {$prefix}pkg install -y " . implode(' ', $missing));
        } catch (RuntimeException) {
            return false;
        }
        return true;
    }
}
