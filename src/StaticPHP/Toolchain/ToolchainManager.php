<?php

declare(strict_types=1);

namespace StaticPHP\Toolchain;

use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Util\GlobalEnvManager;
use StaticPHP\Util\PkgConfigUtil;
use StaticPHP\Util\System\LinuxUtil;

/**
 * Manages the selection and initialization of the appropriate toolchain based on environment variables and system characteristics.
 */
class ToolchainManager
{
    /**
     * Get the toolchain class based on environment variables and OS.
     * For specific toolchain selection, see the method implementation.
     */
    public static function getToolchainClass(): string
    {
        if ($tc = getenv('SPC_TOOLCHAIN')) {
            return $tc;
        }
        $libc = getenv('SPC_LIBC');
        if ($libc && !getenv('SPC_TARGET')) {
            // trigger_error('Setting SPC_LIBC is deprecated, please use SPC_TARGET instead.', E_USER_DEPRECATED);
            return match ($libc) {
                'musl' => LinuxUtil::isMuslDist() ? GccNativeToolchain::class : MuslToolchain::class,
                'glibc' => !LinuxUtil::isMuslDist() ? GccNativeToolchain::class : throw new WrongUsageException('SPC_LIBC must be musl for musl dist.'),
                default => throw new WrongUsageException('Unsupported SPC_LIBC value: ' . $libc),
            };
        }

        return match (PHP_OS_FAMILY) {
            'Linux' => ZigToolchain::class,
            'Windows' => MSVCToolchain::class,
            'Darwin' => ClangNativeToolchain::class,
            default => throw new WrongUsageException('Unsupported OS family: ' . PHP_OS_FAMILY),
        };
    }

    /**
     * Init the toolchain and set it in the container.
     */
    public static function initToolchain(): void
    {
        $toolchainClass = self::getToolchainClass();
        $toolchain = new $toolchainClass();
        ApplicationContext::set(ToolchainInterface::class, $toolchain);
        /* @var ToolchainInterface $toolchainClass */
        $toolchain->initEnv();
        GlobalEnvManager::putenv("SPC_TOOLCHAIN={$toolchainClass}");
    }

    /**
     * Perform post-initialization checks and setups for the toolchain.
     */
    public static function afterInitToolchain(): void
    {
        if (!getenv('SPC_TOOLCHAIN')) {
            throw new WrongUsageException('SPC_TOOLCHAIN was not properly set. Please contact the developers.');
        }
        $musl_wrapper_lib = sprintf('/lib/ld-musl-%s.so.1', php_uname('m'));
        if (SystemTarget::getLibc() === 'musl' && !ApplicationContext::get(ToolchainInterface::class)->isStatic() && !file_exists($musl_wrapper_lib)) {
            throw new WrongUsageException('You are linking against musl libc dynamically, but musl libc is not installed. Please use `bin/spc doctor` to install it.');
        }
        if (SystemTarget::getLibc() === 'glibc' && LinuxUtil::isMuslDist()) {
            throw new WrongUsageException('You are linking against glibc dynamically, which is only supported on glibc distros.');
        }

        // init pkg-config for unix
        if (SystemTarget::isUnix()) {
            if (($found = PkgConfigUtil::findPkgConfig()) !== null) {
                GlobalEnvManager::putenv("PKG_CONFIG={$found}");
            } elseif (!ApplicationContext::has('elephant')) { // skip pkg-config check in elephant mode :P (elephant mode is only for building pkg-config itself)
                throw new WrongUsageException('Cannot find pkg-config executable. Please run `doctor` to fix this.');
            }
        }

        /* @var ToolchainInterface $toolchain */
        $instance = ApplicationContext::get(ToolchainInterface::class);
        $instance->afterInit();
        if (getenv('PHP_BUILD_COMPILER') === false && ($compiler_info = $instance->getCompilerInfo())) {
            GlobalEnvManager::putenv("PHP_BUILD_COMPILER={$compiler_info}");
        }
    }
}
