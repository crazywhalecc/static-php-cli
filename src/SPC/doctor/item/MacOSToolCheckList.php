<?php

declare(strict_types=1);

namespace SPC\doctor\item;

use SPC\builder\traits\UnixSystemUtilTrait;
use SPC\doctor\AsCheckItem;
use SPC\doctor\AsFixItem;
use SPC\doctor\CheckResult;
use SPC\exception\RuntimeException;

class MacOSToolCheckList
{
    use UnixSystemUtilTrait;

    /** @var string[] MacOS 环境下编译依赖的命令 */
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

    #[AsCheckItem('if homebrew has installed', limit_os: 'Darwin', level: 998)]
    public function checkBrew(): ?CheckResult
    {
        // 检查 homebrew 是否已经安装
        if ($this->findCommand('brew') === null) {
            return CheckResult::fail('Homebrew is not installed', 'brew');
        }
        return CheckResult::ok();
    }

    #[AsCheckItem('if necessary tools are installed', limit_os: 'Darwin')]
    public function checkCliTools(): ?CheckResult
    {
        $missing = [];
        foreach (self::REQUIRED_COMMANDS as $cmd) {
            if ($this->findCommand($cmd) === null) {
                $missing[] = $cmd;
            }
        }
        if (!empty($missing)) {
            return CheckResult::fail('missing system commands: ' . implode(', ', $missing), 'build-tools', [$missing]);
        }
        return CheckResult::ok();
    }

    #[AsFixItem('brew')]
    public function fixBrew(): bool
    {
        try {
            shell(true)->exec('/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"');
        } catch (RuntimeException) {
            return false;
        }
        return true;
    }

    #[AsFixItem('build-tools')]
    public function fixBuildTools(array $missing): bool
    {
        foreach ($missing as $cmd) {
            try {
                shell(true)->exec('brew install --formula ' . escapeshellarg($cmd));
            } catch (RuntimeException) {
                return false;
            }
        }
        return true;
    }
}
