<?php

declare(strict_types=1);

namespace StaticPHP\Toolchain;

use StaticPHP\Exception\EnvironmentException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Toolchain\Interface\UnixToolchainInterface;
use StaticPHP\Util\GlobalEnvManager;
use StaticPHP\Util\System\LinuxUtil;
use StaticPHP\Util\System\MacOSUtil;

/**
 * Toolchain implementation for system clang compiler.
 */
class ClangNativeToolchain implements UnixToolchainInterface
{
    public function initEnv(): void
    {
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_CC=clang');
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_CXX=clang++');
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_AR=ar');
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_LD=ld');
    }

    public function afterInit(): void
    {
        foreach (['CC', 'CXX', 'AR', 'LD'] as $env) {
            $command = getenv($env);
            if (!$command || is_file($command)) {
                continue;
            }
            match (PHP_OS_FAMILY) {
                'Linux' => LinuxUtil::findCommand($command) ?? throw new WrongUsageException("{$command} not found, please install it or set {$env} to a valid path."),
                'Darwin' => MacOSUtil::findCommand($command) ?? throw new WrongUsageException("{$command} not found, please install it or set {$env} to a valid path."),
                default => throw new EnvironmentException(__CLASS__ . ' is not supported on ' . PHP_OS_FAMILY . '.'),
            };
        }
    }

    public function getCompilerInfo(): ?string
    {
        $compiler = getenv('CC') ?: 'clang';
        $version = shell(false)->execWithResult("{$compiler} --version", false);
        $head = pathinfo($compiler, PATHINFO_BASENAME);
        if ($version[0] === 0 && preg_match('/clang version (\d+\.\d+\.\d+)/', $version[1][0], $match)) {
            return "{$head} {$match[1]}";
        }
        return $head;
    }

    public function isStatic(): bool
    {
        return PHP_OS_FAMILY === 'Linux' && LinuxUtil::isMuslDist() && !getenv('SPC_MUSL_DYNAMIC');
    }
}
