<?php

declare(strict_types=1);

namespace SPC\toolchain;

use SPC\exception\EnvironmentException;
use SPC\store\pkg\Zig;
use SPC\util\GlobalEnvManager;

class ZigToolchain implements ToolchainInterface
{
    public function initEnv(): void
    {
        // Set environment variables for zig toolchain
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_CC=zig-cc');
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_CXX=zig-c++');
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_AR=zig-ar');
        GlobalEnvManager::putenv('SPC_LINUX_DEFAULT_LD=zig-ld.lld');

        // Generate additional objects needed for zig toolchain
        $paths = ['/usr/lib/gcc', '/usr/local/lib/gcc'];
        $objects = ['crtbeginS.o', 'crtendS.o'];
        $found = [];

        foreach ($objects as $obj) {
            $located = null;
            foreach ($paths as $base) {
                $output = shell_exec("find {$base} -name {$obj} 2>/dev/null | grep -v '/32/' | head -n 1");
                $line = trim((string) $output);
                if ($line !== '') {
                    $located = $line;
                    break;
                }
            }
            if ($located) {
                $found[] = $located;
            }
        }
        GlobalEnvManager::putenv('SPC_EXTRA_RUNTIME_OBJECTS=' . implode(' ', $found));
    }

    public function afterInit(): void
    {
        if (!Zig::isInstalled()) {
            throw new EnvironmentException('You are building with zig, but zig is not installed, please install zig first. (You can use `doctor` command to install it)');
        }
        GlobalEnvManager::addPathIfNotExists(Zig::getPath());
        f_passthru('ulimit -n 2048'); // zig opens extra file descriptors, so when a lot of extensions are built statically, 1024 is not enough
        $cflags = getenv('SPC_DEFAULT_C_FLAGS') ?: '';
        $cxxflags = getenv('SPC_DEFAULT_CXX_FLAGS') ?: '';
        $extraCflags = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') ?: '';
        $cflags = trim($cflags . ' -Wno-date-time');
        $cxxflags = trim($cxxflags . ' -Wno-date-time');
        $extraCflags = trim($extraCflags . ' -Wno-date-time');
        GlobalEnvManager::putenv("SPC_DEFAULT_C_FLAGS={$cflags}");
        GlobalEnvManager::putenv("SPC_DEFAULT_CXX_FLAGS={$cxxflags}");
        GlobalEnvManager::putenv("SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS={$extraCflags}");
        GlobalEnvManager::putenv('RANLIB=zig-ranlib');
        GlobalEnvManager::putenv('OBJCOPY=zig-objcopy');
        $extra_libs = getenv('SPC_EXTRA_LIBS') ?: '';
        if (!str_contains($extra_libs, '-lunwind')) {
            // Add unwind library if not already present
            $extra_libs = trim($extra_libs . ' -lunwind');
            GlobalEnvManager::putenv("SPC_EXTRA_LIBS={$extra_libs}");
        }
        $cflags = getenv('SPC_DEFAULT_C_FLAGS') ?: getenv('CFLAGS') ?: '';
        $has_avx512 = str_contains($cflags, '-mavx512') || str_contains($cflags, '-march=x86-64-v4');
        if (!$has_avx512) {
            $extra_vars = getenv('SPC_EXTRA_PHP_VARS') ?: '';
            GlobalEnvManager::putenv("SPC_EXTRA_PHP_VARS=php_cv_have_avx512=no php_cv_have_avx512vbmi=no {$extra_vars}");
        }
    }

    public function getCompilerInfo(): ?string
    {
        $version = shell(false)->execWithResult('zig version', false)[1][0] ?? '';
        return trim("zig {$version}");
    }
}
