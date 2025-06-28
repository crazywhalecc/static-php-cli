<?php

declare(strict_types=1);

namespace SPC\util\toolchain;

use SPC\builder\freebsd\SystemUtil as FreeBSDSystemUtil;
use SPC\builder\linux\SystemUtil as LinuxSystemUtil;
use SPC\builder\macos\SystemUtil as MacOSSystemUtil;
use SPC\exception\WrongUsageException;
use SPC\util\GlobalEnvManager;
use SPC\util\SPCTarget;

class GccNativeToolchain implements ToolchainInterface
{
    public function initEnv(string $target): void
    {
        // native toolchain does not support versioning
        if (SPCTarget::getTargetSuffix() !== null) {
            throw new WrongUsageException('gcc native toolchain does not support versioning.');
        }
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_CC=gcc');
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_CXX=g++');
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_AR=ar');
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_LD=ld.gold');
    }

    public function afterInit(string $target): void
    {
        // check gcc exists
        match (PHP_OS_FAMILY) {
            'Linux' => LinuxSystemUtil::findCommand('g++') ?? throw new WrongUsageException('g++ not found, please install it or set CC/CXX to a valid path.'),
            'Darwin' => MacOSSystemUtil::findCommand('g++') ?? throw new WrongUsageException('g++ not found, please install it or set CC/CXX to a valid path.'),
            'BSD' => FreeBSDSystemUtil::findCommand('g++') ?? throw new WrongUsageException('g++ not found, please install it or set CC/CXX to a valid path.'),
            default => throw new \RuntimeException('GCC is not supported on ' . PHP_OS_FAMILY . '.'),
        };
    }
}
