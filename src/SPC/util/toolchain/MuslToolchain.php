<?php

declare(strict_types=1);

namespace SPC\util\toolchain;

use SPC\exception\WrongUsageException;
use SPC\util\GlobalEnvManager;
use SPC\util\SPCTarget;

class MuslToolchain implements ToolchainInterface
{
    public function initEnv(string $target): void
    {
        // Check if the target is musl-static (the musl(-shared) target is not supported yet)
        if (!in_array($target, [SPCTarget::MUSL_STATIC/* , SPCTarget::MUSL */], true)) {
            throw new WrongUsageException('MuslToolchain can only be used with the "musl-static" target.');
        }
        $arch = getenv('GNU_ARCH');
        // Set environment variables for musl toolchain
        GlobalEnvManager::putenv("SPC_LINUX_DEFAULT_CC={$arch}-linux-musl-gcc");
        GlobalEnvManager::putenv("SPC_LINUX_DEFAULT_CXX={$arch}-linux-musl-g++");
        GlobalEnvManager::putenv("SPC_LINUX_DEFAULT_AR={$arch}-linux-musl-ar");
        GlobalEnvManager::putenv("SPC_LINUX_DEFAULT_LD={$arch}-linux-musl-ld");
        GlobalEnvManager::addPathIfNotExists('/usr/local/musl/bin');
        GlobalEnvManager::addPathIfNotExists("/usr/local/musl/{$arch}-linux-musl/bin");

        GlobalEnvManager::putenv("SPC_LINUX_DEFAULT_LD_LIBRARY_PATH=/usr/local/musl/lib:/usr/local/musl/{$arch}-linux-musl/lib");
        GlobalEnvManager::putenv("SPC_LINUX_DEFAULT_LIBRARY_PATH=/usr/local/musl/lib:/usr/local/musl/{$arch}-linux-musl/lib");
    }

    public function afterInit(string $target): void
    {
        $arch = getenv('GNU_ARCH');
        // append LD_LIBRARY_PATH to $configure = getenv('SPC_CMD_PREFIX_PHP_CONFIGURE');
        $configure = getenv('SPC_CMD_PREFIX_PHP_CONFIGURE');
        $ld_library_path = getenv('SPC_LINUX_DEFAULT_LD_LIBRARY_PATH');
        GlobalEnvManager::putenv("SPC_CMD_PREFIX_PHP_CONFIGURE=LD_LIBRARY_PATH=\"{$ld_library_path}\" {$configure}");

        if (!file_exists("/usr/local/musl/{$arch}-linux-musl/lib/libc.a")) {
            throw new WrongUsageException('You are building with musl-libc target in glibc distro, but musl-toolchain is not installed, please install musl-toolchain first. (You can use `doctor` command to install it)');
        }
    }
}
