<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\Config\PackageConfig;
use StaticPHP\Exception\PatchException;
use StaticPHP\Util\FileSystem;

/**
 * Represents a library package with platform-specific build functions.
 */
class LibraryPackage extends Package
{
    /** @var array<string, callable> $build_functions Build functions for different OS binding */
    protected array $build_functions = [];

    /**
     * Add a build function for a specific platform.
     *
     * @param string   $platform PHP_OS_FAMILY
     * @param callable $func     Function to build for the platform
     */
    public function addBuildFunction(string $platform, callable $func): void
    {
        $this->build_functions[$platform] = $func;
        if ($platform === PHP_OS_FAMILY) {
            $this->addStage('build', $func);
        }
    }

    public function isInstalled(): bool
    {
        foreach (PackageConfig::get($this->getName(), 'static-libs', []) as $lib) {
            if (!file_exists("{$this->getLibDir()}/{$lib}")) {
                return false;
            }
        }
        foreach (PackageConfig::get($this->getName(), 'headers', []) as $header) {
            if (!file_exists("{$this->getIncludeDir()}/{$header}")) {
                return false;
            }
        }
        foreach (PackageConfig::get($this->getName(), 'pkg-configs', []) as $pc) {
            if (!file_exists("{$this->getLibDir()}/pkgconfig/{$pc}.pc")) {
                return false;
            }
        }
        foreach (PackageConfig::get($this->getName(), 'static-bins', []) as $bin) {
            if (!file_exists("{$this->getBinDir()}/{$bin}")) {
                return false;
            }
        }
        return true;
    }

    public function patchLaDependencyPrefix(?array $files = null): void
    {
        logger()->info("Patching library {$this->name} la files");
        $throwOnMissing = true;
        if ($files === null) {
            $files = PackageConfig::get($this->getName(), 'static-libs', []);
            $files = array_map(fn ($name) => str_replace('.a', '.la', $name), $files);
            $throwOnMissing = false;
        }
        foreach ($files as $name) {
            $realpath = realpath(BUILD_LIB_PATH . '/' . $name);
            if ($realpath === false) {
                if ($throwOnMissing) {
                    throw new PatchException('la dependency patcher', "Cannot find library [{$this->name}] la file [{$name}] !");
                }
                logger()->warning(message: 'Cannot find library [' . $this->name . '] la file [' . $name . '] !');
                continue;
            }
            logger()->debug('Patching ' . $realpath);
            // replace prefix
            $file = FileSystem::readFile($realpath);
            $file = str_replace(
                ' /lib/',
                ' ' . BUILD_LIB_PATH . '/',
                $file
            );
            $file = preg_replace('/^libdir=.*$/m', "libdir='" . BUILD_LIB_PATH . "'", $file);
            FileSystem::writeFile($realpath, $file);
        }
    }

    /**
     * Get extra CFLAGS for current package.
     * You need to define the environment variable in the format of {LIBRARY_NAME}_CFLAGS
     * where {LIBRARY_NAME} is the snake_case name of the library.
     * For example, for libjpeg, the environment variable should be libjpeg_CFLAGS.
     */
    public function getLibExtraCFlags(): string
    {
        // get environment variable
        $env = getenv($this->getSnakeCaseName() . '_CFLAGS') ?: '';
        // get default c flags
        $arch_c_flags = getenv('SPC_DEFAULT_C_FLAGS') ?: '';
        if (!empty(getenv('SPC_DEFAULT_C_FLAGS')) && !str_contains($env, $arch_c_flags)) {
            $env .= ' ' . $arch_c_flags;
        }
        return trim($env);
    }

    /**
     * Get extra CXXFLAGS for current package.
     * You need to define the environment variable in the format of {LIBRARY_NAME}_CXXFLAGS
     * where {LIBRARY_NAME} is the snake_case name of the library.
     * For example, for libjpeg, the environment variable should be libjpeg_CXXFLAGS.
     */
    public function getLibExtraCxxFlags(): string
    {
        // get environment variable
        $env = getenv($this->getSnakeCaseName() . '_CXXFLAGS') ?: '';
        // get default cxx flags
        $arch_cxx_flags = getenv('SPC_DEFAULT_CXX_FLAGS') ?: '';
        if (!empty(getenv('SPC_DEFAULT_CXX_FLAGS')) && !str_contains($env, $arch_cxx_flags)) {
            $env .= ' ' . $arch_cxx_flags;
        }
        return trim($env);
    }

    /**
     * Get extra LDFLAGS for current package.
     * You need to define the environment variable in the format of {LIBRARY_NAME}_LDFLAGS
     * where {LIBRARY_NAME} is the snake_case name of the library.
     * For example, for libjpeg, the environment variable should be libjpeg_LDFLAGS.
     */
    public function getLibExtraLdFlags(): string
    {
        // get environment variable
        $env = getenv($this->getSnakeCaseName() . '_LDFLAGS') ?: '';
        // get default ld flags
        $arch_ld_flags = getenv('SPC_DEFAULT_LD_FLAGS') ?: '';
        if (!empty(getenv('SPC_DEFAULT_LD_FLAGS')) && !str_contains($env, $arch_ld_flags)) {
            $env .= ' ' . $arch_ld_flags;
        }
        return trim($env);
    }

    /**
     * Get extra LIBS for current package.
     * You need to define the environment variable in the format of {LIBRARY_NAME}_LIBS
     * where {LIBRARY_NAME} is the snake_case name of the library.
     * For example, for libjpeg, the environment variable should be libjpeg_LIBS.
     */
    public function getLibExtraLibs(): string
    {
        return getenv($this->getSnakeCaseName() . '_LIBS') ?: '';
    }

    /**
     * Get the build root path for the package.
     *
     * TODO: Can be changed to support per-package build root path in the future.
     */
    public function getBuildRootPath(): string
    {
        return BUILD_ROOT_PATH;
    }

    /**
     * Get the include directory for the package.
     *
     * TODO: Can be changed to support per-package include directory in the future.
     */
    public function getIncludeDir(): string
    {
        return BUILD_INCLUDE_PATH;
    }

    /**
     * Get the library directory for the package.
     *
     * TODO: Can be changed to support per-package library directory in the future.
     */
    public function getLibDir(): string
    {
        return BUILD_LIB_PATH;
    }

    public function getBinDir(): string
    {
        return BUILD_BIN_PATH;
    }
}
