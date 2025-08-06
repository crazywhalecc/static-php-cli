<?php

declare(strict_types=1);

namespace SPC\toolchain;

use SPC\exception\EnvironmentException;
use SPC\util\GlobalEnvManager;

class MuslToolchain implements ToolchainInterface
{
    public function initEnv(): void
    {
        $arch = getenv('GNU_ARCH');
        // Set environment variables for musl toolchain
        GlobalEnvManager::putenv("SPC_LINUX_DEFAULT_CC={$arch}-linux-musl-gcc");
        GlobalEnvManager::putenv("SPC_LINUX_DEFAULT_CXX={$arch}-linux-musl-g++");
        GlobalEnvManager::putenv("SPC_LINUX_DEFAULT_AR={$arch}-linux-musl-ar");
        GlobalEnvManager::putenv("SPC_LINUX_DEFAULT_LD={$arch}-linux-musl-ld");
        GlobalEnvManager::addPathIfNotExists('/usr/local/musl/bin');
        GlobalEnvManager::addPathIfNotExists("/usr/local/musl/{$arch}-linux-musl/bin");

        GlobalEnvManager::putenv("SPC_LD_LIBRARY_PATH=/usr/local/musl/lib:/usr/local/musl/{$arch}-linux-musl/lib");
        GlobalEnvManager::putenv("SPC_LIBRARY_PATH=/usr/local/musl/lib:/usr/local/musl/{$arch}-linux-musl/lib");
    }

    public function afterInit(): void
    {
        $arch = getenv('GNU_ARCH');
        // append LD_LIBRARY_PATH to $configure = getenv('SPC_CMD_PREFIX_PHP_CONFIGURE');
        $configure = getenv('SPC_CMD_PREFIX_PHP_CONFIGURE');
        $ld_library_path = getenv('SPC_LD_LIBRARY_PATH');
        GlobalEnvManager::putenv("SPC_CMD_PREFIX_PHP_CONFIGURE=LD_LIBRARY_PATH=\"{$ld_library_path}\" {$configure}");

        if (!file_exists("/usr/local/musl/{$arch}-linux-musl/lib/libc.a")) {
            throw new EnvironmentException('You are building with musl-libc target in glibc distro, but musl-toolchain is not installed, please install musl-toolchain first. (You can use `doctor` command to install it)');
        }
    }

    public function getCompilerInfo(): ?string
    {
        $compiler = getenv('CC') ?: getenv('SPC_LINUX_DEFAULT_CC');
        $version = shell(false)->execWithResult("{$compiler} --version", false);
        $head = pathinfo($compiler, PATHINFO_BASENAME);
        if ($version[0] === 0 && preg_match('/linux-musl-cc.*(\d+.\d+.\d+)/', $version[1][0], $match)) {
            return "{$head} {$match[1]}";
        }
        return $head;
    }
}
