<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\PatchException;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\DependencyResolver;
use StaticPHP\Util\DirDiff;
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
     * Register default stages if not already defined by attributes.
     * This is called after all attributes have been loaded.
     *
     * @internal Called by PackageLoader after loading attributes
     */
    public function registerDefaultStages(): void
    {
        if (!$this->hasStage('packPrebuilt')) {
            $this->addStage('packPrebuilt', [$this, 'packPrebuilt']);
        }
        // counting files before build stage
    }

    /**
     * Pack the prebuilt library into an archive.
     *
     * @internal this function is intended to be called by the dev:pack-lib command only
     */
    public function packPrebuilt(): void
    {
        $target_dir = WORKING_DIR . '/dist';
        $placeholder_file = BUILD_ROOT_PATH . '/.spc-extract-placeholder.json';

        if (!ApplicationContext::has(DirDiff::class)) {
            throw new SPCInternalException('pack-dirdiff context not found for packPrebuilt stage. You cannot call "packPrebuilt" function manually.');
        }
        // check whether this library has correctly installed files
        if (!$this->isInstalled()) {
            throw new ValidationException("Cannot pack prebuilt library [{$this->getName()}] because it is not fully installed.");
        }
        // get after-build buildroot file list
        $increase_files = ApplicationContext::get(DirDiff::class)->getIncrementFiles(true);

        FileSystem::createDir($target_dir);

        // before pack, check if the dependency tree contains lib-suggests
        $libraries = DependencyResolver::resolve([$this], include_suggests: true);
        foreach ($libraries as $lib) {
            if (PackageConfig::get($lib, 'suggests', []) !== []) {
                throw new ValidationException("The library {$lib} has lib-suggests, packing [{$this->name}] is not safe, abort !");
            }
        }

        $origin_files = [];

        // get pack placehoder defines
        $placehoder = get_pack_replace();

        // patch pkg-config and la files with absolute path
        foreach ($increase_files as $file) {
            if (str_ends_with($file, '.pc') || str_ends_with($file, '.la')) {
                $content = FileSystem::readFile(BUILD_ROOT_PATH . '/' . $file);
                $origin_files[$file] = $content;
                // replace relative paths with absolute paths
                $content = str_replace(
                    array_keys($placehoder),
                    array_values($placehoder),
                    $content
                );
                FileSystem::writeFile(BUILD_ROOT_PATH . '/' . $file, $content);
            }
        }

        // add .spc-extract-placeholder.json in BUILD_ROOT_PATH
        file_put_contents($placeholder_file, json_encode(array_keys($origin_files), JSON_PRETTY_PRINT));
        $increase_files[] = '.spc-extract-placeholder.json';

        // every file mapped with BUILD_ROOT_PATH
        // get BUILD_ROOT_PATH last dir part
        $buildroot_part = basename(BUILD_ROOT_PATH);
        $increase_files = array_map(fn ($file) => $buildroot_part . '/' . $file, $increase_files);
        // write list to packlib_files.txt
        FileSystem::writeFile(WORKING_DIR . '/packlib_files.txt', implode("\n", $increase_files));
        // pack
        $filename = match (SystemTarget::getTargetOS()) {
            'Windows' => '{name}-{arch}-{os}.tgz',
            'Darwin' => '{name}-{arch}-{os}.txz',
            'Linux' => '{name}-{arch}-{os}-{libc}-{libcver}.txz',
            default => throw new WrongUsageException('Unsupported OS for packing prebuilt library: ' . SystemTarget::getTargetOS()),
        };
        $replace = [
            '{name}' => $this->getName(),
            '{arch}' => arch2gnu(php_uname('m')),
            '{os}' => strtolower(PHP_OS_FAMILY),
            '{libc}' => SystemTarget::getLibc() ?? 'default',
            '{libcver}' => SystemTarget::getLibcVersion() ?? 'default',
        ];
        // detect suffix, for proper tar option
        $tar_option = $this->getTarOptionFromSuffix($filename);
        $filename = str_replace(array_keys($replace), array_values($replace), $filename);
        $filename = $target_dir . '/' . $filename;
        f_passthru("tar {$tar_option} {$filename} -T " . WORKING_DIR . '/packlib_files.txt');
        logger()->info('Pack library ' . $this->getName() . ' to ' . $filename . ' complete.');

        // remove temp files
        unlink($placeholder_file);

        foreach ($origin_files as $file => $content) {
            // restore original files
            if (file_exists(BUILD_ROOT_PATH . '/' . $file)) {
                FileSystem::writeFile(BUILD_ROOT_PATH . '/' . $file, $content);
            }
        }

        // remove dirdiff
        ApplicationContext::set(DirDiff::class, null);
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

    /**
     * Get tar compress options from suffix
     *
     * @param  string $name Package file name
     * @return string Tar options for packaging libs
     */
    private function getTarOptionFromSuffix(string $name): string
    {
        if (str_ends_with($name, '.tar')) {
            return '-cf';
        }
        if (str_ends_with($name, '.tar.gz') || str_ends_with($name, '.tgz')) {
            return '-czf';
        }
        if (str_ends_with($name, '.tar.bz2') || str_ends_with($name, '.tbz2')) {
            return '-cjf';
        }
        if (str_ends_with($name, '.tar.xz') || str_ends_with($name, '.txz')) {
            return '-cJf';
        }
        if (str_ends_with($name, '.tar.lz') || str_ends_with($name, '.tlz')) {
            return '-c --lzma -f';
        }
        return '-cf';
    }
}
