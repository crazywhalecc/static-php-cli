<?php

declare(strict_types=1);

namespace SPC\toolchain;

use SPC\builder\freebsd\SystemUtil as FreeBSDSystemUtil;
use SPC\builder\linux\SystemUtil as LinuxSystemUtil;
use SPC\builder\macos\SystemUtil as MacOSSystemUtil;
use SPC\exception\EnvironmentException;
use SPC\exception\WrongUsageException;
use SPC\util\GlobalEnvManager;

/**
 * Toolchain implementation for system clang compiler.
 */
class ClangNativeToolchain implements ToolchainInterface
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
                'Linux' => LinuxSystemUtil::findCommand($command) ?? throw new WrongUsageException("{$command} not found, please install it or set {$env} to a valid path."),
                'Darwin' => MacOSSystemUtil::findCommand($command) ?? throw new WrongUsageException("{$command} not found, please install it or set {$env} to a valid path."),
                'BSD' => FreeBSDSystemUtil::findCommand($command) ?? throw new WrongUsageException("{$command} not found, please install it or set {$env} to a valid path."),
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
}
