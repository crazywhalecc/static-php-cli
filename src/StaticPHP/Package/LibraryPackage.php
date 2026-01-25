<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\Config\PackageConfig;
use StaticPHP\Exception\PatchException;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\SPCConfigUtil;

/**
 * Represents a library package with platform-specific build functions.
 */
class LibraryPackage extends Package
{
    public function isInstalled(): bool
    {
        foreach (PackageConfig::get($this->getName(), 'static-libs', []) as $lib) {
            $path = FileSystem::isRelativePath($lib) ? "{$this->getLibDir()}/{$lib}" : $lib;
            if (!file_exists($path)) {
                return false;
            }
        }
        foreach (PackageConfig::get($this->getName(), 'headers', []) as $header) {
            $path = FileSystem::isRelativePath($header) ? "{$this->getIncludeDir()}/{$header}" : $header;
            if (!file_exists($path)) {
                return false;
            }
        }
        foreach (PackageConfig::get($this->getName(), 'pkg-configs', []) as $pc) {
            if (!str_ends_with($pc, '.pc')) {
                $pc .= '.pc';
            }
            if (!file_exists("{$this->getLibDir()}/pkgconfig/{$pc}")) {
                return false;
            }
        }
        foreach (PackageConfig::get($this->getName(), 'static-bins', []) as $bin) {
            $path = FileSystem::isRelativePath($bin) ? "{$this->getBinDir()}/{$bin}" : $bin;
            if (!file_exists($path)) {
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
     * Patch pkgconfig file prefix, exec_prefix, libdir, includedir for correct build.
     *
     * @param array      $files          File list to patch, if empty, will use pkg-configs from config (e.g. ['zlib.pc', 'openssl.pc'])
     * @param int        $patch_option   Patch options
     * @param null|array $custom_replace Custom replace rules, if provided, will be used to replace in the format [regex, replacement]
     */
    public function patchPkgconfPrefix(array $files = [], int $patch_option = PKGCONF_PATCH_ALL, ?array $custom_replace = null): void
    {
        logger()->info("Patching library [{$this->getName()}] pkgconfig");
        if ($files === [] && ($conf_pc = PackageConfig::get($this->getName(), 'pkg-configs', [])) !== []) {
            $files = array_map(fn ($x) => "{$x}.pc", $conf_pc);
        }
        foreach ($files as $name) {
            $realpath = realpath("{$this->getLibDir()}/pkgconfig/{$name}");
            if ($realpath === false) {
                throw new PatchException('pkg-config prefix patcher', "Cannot find library [{$this->getName()}] pkgconfig file [{$name}] in {$this->getLibDir()}/pkgconfig/ !");
            }
            logger()->debug("Patching {$realpath}");
            // replace prefix
            $file = FileSystem::readFile($realpath);
            $file = ($patch_option & PKGCONF_PATCH_PREFIX) === PKGCONF_PATCH_PREFIX ? preg_replace('/^prefix\s*=.*$/m', 'prefix=' . BUILD_ROOT_PATH, $file) : $file;
            $file = ($patch_option & PKGCONF_PATCH_EXEC_PREFIX) === PKGCONF_PATCH_EXEC_PREFIX ? preg_replace('/^exec_prefix\s*=.*$/m', 'exec_prefix=${prefix}', $file) : $file;
            $file = ($patch_option & PKGCONF_PATCH_LIBDIR) === PKGCONF_PATCH_LIBDIR ? preg_replace('/^libdir\s*=.*$/m', 'libdir=${prefix}/lib', $file) : $file;
            $file = ($patch_option & PKGCONF_PATCH_INCLUDEDIR) === PKGCONF_PATCH_INCLUDEDIR ? preg_replace('/^includedir\s*=.*$/m', 'includedir=${prefix}/include', $file) : $file;
            $file = ($patch_option & PKGCONF_PATCH_CUSTOM) === PKGCONF_PATCH_CUSTOM && $custom_replace !== null ? preg_replace($custom_replace[0], $custom_replace[1], $file) : $file;
            FileSystem::writeFile($realpath, $file);
        }
    }

    /**
     * Get static library files for current package and its dependencies.
     */
    public function getStaticLibFiles(): string
    {
        $config = new SPCConfigUtil(['libs_only_deps' => true, 'absolute_libs' => true]);
        $res = $config->config([$this->getName()]);
        return $res['libs'];
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
