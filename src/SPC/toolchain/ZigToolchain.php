<?php

declare(strict_types=1);

namespace SPC\toolchain;

use SPC\exception\WrongUsageException;
use SPC\store\pkg\Zig;
use SPC\util\GlobalEnvManager;

class ZigToolchain implements ToolchainInterface
{
    public function initEnv(): void
    {
        // Set environment variables for zig toolchain
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_CC=zig-cc');
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_CXX=zig-c++');
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_AR=ar');
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_LD=ld');
    }

    public function afterInit(): void
    {
        if (!is_dir(Zig::getEnvironment()['PATH'])) {
            throw new WrongUsageException('You are building with zig, but zig is not installed, please install zig first. (You can use `doctor` command to install it)');
        }
        GlobalEnvManager::addPathIfNotExists(Zig::getEnvironment()['PATH']);
    }
}
