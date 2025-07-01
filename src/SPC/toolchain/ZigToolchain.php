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
        $arch = getenv('GNU_ARCH');
        // Set environment variables for musl toolchain
        GlobalEnvManager::putenv("SPC_LINUX_DEFAULT_CC=zig-cc");
        GlobalEnvManager::putenv("SPC_LINUX_DEFAULT_CXX=zig-c++");
        GlobalEnvManager::putenv("SPC_LINUX_DEFAULT_AR=ar");
        GlobalEnvManager::putenv("SPC_LINUX_DEFAULT_LD=ld");
        GlobalEnvManager::addPathIfNotExists('/usr/local/musl/bin');
        GlobalEnvManager::addPathIfNotExists("/usr/local/musl/{$arch}-linux-musl/bin");

        GlobalEnvManager::putenv("SPC_LD_LIBRARY_PATH=/usr/local/musl/lib:/usr/local/musl/{$arch}-linux-musl/lib");
        GlobalEnvManager::putenv("SPC_LIBRARY_PATH=/usr/local/musl/lib:/usr/local/musl/{$arch}-linux-musl/lib");
    }

    public function afterInit(): void
    {
        if (!is_dir(Zig::getEnvironment()['PATH'])) {
            throw new WrongUsageException('You are building with zig, but zig is not installed, please install zig first. (You can use `doctor` command to install it)');
        }
    }
}
