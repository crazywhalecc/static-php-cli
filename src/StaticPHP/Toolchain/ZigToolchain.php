<?php

declare(strict_types=1);

namespace StaticPHP\Toolchain;

use StaticPHP\Toolchain\Interface\UnixToolchainInterface;
use StaticPHP\Util\GlobalEnvManager;
use StaticPHP\Util\System\LinuxUtil;

class ZigToolchain implements UnixToolchainInterface
{
    public function initEnv(): void
    {
        // Set environment variables for zig toolchain
        GlobalEnvManager::putenv('SPC_DEFAULT_CC=zig-cc');
        GlobalEnvManager::putenv('SPC_DEFAULT_CXX=zig-c++');
        GlobalEnvManager::putenv('SPC_DEFAULT_AR=zig-ar');
        GlobalEnvManager::putenv('SPC_DEFAULT_RANLIB=zig-ranlib');
        GlobalEnvManager::putenv('SPC_DEFAULT_LD=zig-ld.lld');
    }

    public function afterInit(): void
    {
        GlobalEnvManager::addPathIfNotExists($this->getPath());
        f_passthru('ulimit -n 2048'); // zig opens extra file descriptors, so when a lot of extensions are built statically, 1024 is not enough
        $cflags = getenv('SPC_DEFAULT_CFLAGS') ?: '';
        $cxxflags = getenv('SPC_DEFAULT_CXXFLAGS') ?: '';
        $extraCflags = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') ?: '';
        $cflags = trim($cflags . ' -Wno-date-time');
        $cxxflags = trim($cxxflags . ' -Wno-date-time');
        $extraCflags = trim($extraCflags . ' -Wno-date-time');
        GlobalEnvManager::putenv("SPC_DEFAULT_CFLAGS={$cflags}");
        GlobalEnvManager::putenv("SPC_DEFAULT_CXXFLAGS={$cxxflags}");
        GlobalEnvManager::putenv("SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS={$extraCflags}");
        GlobalEnvManager::putenv('RANLIB=zig-ranlib');
        GlobalEnvManager::putenv('OBJCOPY=zig-objcopy');
        $compiler_extra = getenv('SPC_COMPILER_EXTRA') ?: '';
        if (!str_contains($compiler_extra, '-fno-sanitize=undefined')) {
            GlobalEnvManager::putenv('SPC_COMPILER_EXTRA=' . trim($compiler_extra . ' -fno-sanitize=undefined'));
        }
        $extra_libs = getenv('SPC_EXTRA_LIBS') ?: '';
        if (!str_contains($extra_libs, '-lunwind')) {
            // Add unwind library if not already present
            $extra_libs = trim($extra_libs . ' -lunwind');
            GlobalEnvManager::putenv("SPC_EXTRA_LIBS={$extra_libs}");
        }
        $cflags = getenv('SPC_DEFAULT_CFLAGS') ?: getenv('CFLAGS') ?: '';
        $has_avx512 = str_contains($cflags, '-mavx512') || str_contains($cflags, '-march=x86-64-v4');
        if (!$has_avx512) {
            $extra_vars = getenv('SPC_EXTRA_PHP_VARS') ?: '';
            GlobalEnvManager::putenv("SPC_EXTRA_PHP_VARS=php_cv_have_avx512=no php_cv_have_avx512vbmi=no {$extra_vars}");
        }
        // zig-cc/clang treats strlcpy/strlcat as compiler builtins, so configure link tests pass (HAVE_STRLCPY=1)
        $extra_vars = getenv('SPC_EXTRA_PHP_VARS') ?: '';
        GlobalEnvManager::putenv("SPC_EXTRA_PHP_VARS=ac_cv_func_strlcpy=no ac_cv_func_strlcat=no {$extra_vars}");
    }

    public function getCompilerInfo(): ?string
    {
        $version = shell(false)->execWithResult('zig version', false)[1][0] ?? '';
        return trim("zig {$version}");
    }

    public function isStatic(): bool
    {
        // if SPC_LIBC is set, it means the target is static, remove it when 3.0 is released
        if ($target = getenv('SPC_TARGET')) {
            if (str_contains($target, '-macos') || str_contains($target, '-native') && PHP_OS_FAMILY === 'Darwin') {
                return false;
            }
            if (str_contains($target, '-gnu')) {
                return false;
            }
            if (str_contains($target, '-dynamic')) {
                return false;
            }
            if (str_contains($target, '-musl')) {
                return true;
            }
            if (PHP_OS_FAMILY === 'Linux') {
                return LinuxUtil::isMuslDist();
            }
            return true;
        }
        if (getenv('SPC_LIBC') === 'musl') {
            return true;
        }
        return false;
    }

    private function getPath(): string
    {
        return PKG_ROOT_PATH . '/zig';
    }
}
