<?php

declare(strict_types=1);

namespace SPC\util\toolchain;

use SPC\builder\freebsd\SystemUtil as FreeBSDSystemUtil;
use SPC\builder\linux\SystemUtil as LinuxSystemUtil;
use SPC\builder\macos\SystemUtil as MacOSSystemUtil;
use SPC\exception\WrongUsageException;
use SPC\util\GlobalEnvManager;

class ClangNativeToolchain implements ToolchainInterface
{
    public function initEnv(string $target): void
    {
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_CC=clang');
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_CXX=clang++');
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_AR=ar');
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_LD=ld');
    }

    public function afterInit(string $target): void
    {
        // check clang exists
        match (PHP_OS_FAMILY) {
            'Linux' => LinuxSystemUtil::findCommand('clang++') ?? throw new WrongUsageException('Clang++ not found, please install it or manually set CC/CXX to a valid path.'),
            'Darwin' => MacOSSystemUtil::findCommand('clang++') ?? throw new WrongUsageException('Clang++ not found, please install it or set CC/CXX to a valid path.'),
            'BSD' => FreeBSDSystemUtil::findCommand('clang++') ?? throw new WrongUsageException('Clang++ not found, please install it or set CC/CXX to a valid path.'),
            default => throw new WrongUsageException('Clang is not supported on ' . PHP_OS_FAMILY . '.'),
        };
    }
}
