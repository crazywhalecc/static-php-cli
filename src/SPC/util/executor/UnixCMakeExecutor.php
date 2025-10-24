<?php

declare(strict_types=1);

namespace SPC\util\executor;

use SPC\builder\freebsd\library\BSDLibraryBase;
use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\store\FileSystem;
use SPC\util\PkgConfigUtil;
use SPC\util\shell\UnixShell;

/**
 * Unix-like OS cmake command executor.
 */
class UnixCMakeExecutor extends Executor
{
    protected UnixShell $shell;

    protected array $configure_args = [];

    protected ?string $build_dir = null;

    protected ?array $custom_default_args = null;

    protected int $steps = 3;

    protected bool $reset = true;

    public function __construct(protected BSDLibraryBase|LinuxLibraryBase|MacOSLibraryBase $library)
    {
        parent::__construct($library);
        $this->initShell();
    }

    public function build(string $build_pos = '..'): void
    {
        // set cmake dir
        $this->initBuildDir();

        if ($this->reset) {
            FileSystem::resetDir($this->build_dir);
        }

        $this->shell = $this->shell->cd($this->build_dir);

        // config
        $this->steps >= 1 && $this->shell->exec("cmake {$this->getConfigureArgs()} {$this->getDefaultCMakeArgs()} {$build_pos}");

        // make
        $this->steps >= 2 && $this->shell->exec("cmake --build . -j {$this->library->getBuilder()->concurrency}");

        // install
        $this->steps >= 3 && $this->shell->exec('make install');
    }

    /**
     * Add optional library configuration.
     * This method checks if a library is available and adds the corresponding arguments to the CMake configuration.
     *
     * @param  string          $name       library name to check
     * @param  \Closure|string $true_args  arguments to use if the library is available (allow closure, returns string)
     * @param  string          $false_args arguments to use if the library is not available
     * @return $this
     */
    public function optionalLib(string $name, \Closure|string $true_args, string $false_args = ''): static
    {
        if ($get = $this->library->getBuilder()->getLib($name)) {
            logger()->info("Building library [{$this->library->getName()}] with {$name} support");
            $args = $true_args instanceof \Closure ? $true_args($get) : $true_args;
        } else {
            logger()->info("Building library [{$this->library->getName()}] without {$name} support");
            $args = $false_args;
        }
        $this->addConfigureArgs($args);
        return $this;
    }

    /**
     * Add configure args.
     */
    public function addConfigureArgs(...$args): static
    {
        $this->configure_args = [...$this->configure_args, ...$args];
        return $this;
    }

    public function appendEnv(array $env): static
    {
        $this->shell->appendEnv($env);
        return $this;
    }

    /**
     * To build steps.
     *
     * @param  int   $step Step number, accept 1-3
     * @return $this
     */
    public function toStep(int $step): static
    {
        $this->steps = $step;
        return $this;
    }

    /**
     * Set custom CMake build directory.
     *
     * @param string $dir custom CMake build directory
     */
    public function setBuildDir(string $dir): static
    {
        $this->build_dir = $dir;
        return $this;
    }

    /**
     * Set the custom default args.
     */
    public function setCustomDefaultArgs(...$args): static
    {
        $this->custom_default_args = $args;
        return $this;
    }

    /**
     * Set the reset status.
     * If we set it to false, it will not clean and create the specified cmake working directory.
     */
    public function setReset(bool $reset): static
    {
        $this->reset = $reset;
        return $this;
    }

    /**
     * Get configure argument line.
     */
    private function getConfigureArgs(): string
    {
        return implode(' ', $this->configure_args);
    }

    private function getDefaultCMakeArgs(): string
    {
        return implode(' ', $this->custom_default_args ?? [
            '-DCMAKE_BUILD_TYPE=Release',
            "-DCMAKE_INSTALL_PREFIX={$this->library->getBuildRootPath()}",
            '-DCMAKE_INSTALL_BINDIR=bin',
            '-DCMAKE_INSTALL_LIBDIR=lib',
            '-DCMAKE_INSTALL_INCLUDEDIR=include',
            '-DPOSITION_INDEPENDENT_CODE=ON',
            '-DBUILD_SHARED_LIBS=OFF',
            "-DCMAKE_TOOLCHAIN_FILE={$this->makeCmakeToolchainFile()}",
        ]);
    }

    /**
     * Initialize the CMake build directory.
     * If the directory is not set, it defaults to the library's source directory with '/build' appended.
     */
    private function initBuildDir(): void
    {
        if ($this->build_dir === null) {
            $this->build_dir = "{$this->library->getSourceDir()}/build";
        }
    }

    /**
     * Generate cmake toolchain file for current spc instance, and return the file path.
     *
     * @return string CMake toolchain file path
     */
    private function makeCmakeToolchainFile(): string
    {
        static $created;
        if (isset($created)) {
            return $created;
        }
        $os = PHP_OS_FAMILY;
        $target_arch = arch2gnu(php_uname('m'));
        $cflags = getenv('SPC_DEFAULT_C_FLAGS');
        $cc = getenv('CC');
        $cxx = getenv('CCX');
        logger()->debug("making cmake tool chain file for {$os} {$target_arch} with CFLAGS='{$cflags}'");
        $root = BUILD_ROOT_PATH;
        $pkgConfigExecutable = PkgConfigUtil::findPkgConfig();
        $ccLine = '';
        if ($cc) {
            $ccLine = 'SET(CMAKE_C_COMPILER ' . $cc . ')';
        }
        $cxxLine = '';
        if ($cxx) {
            $cxxLine = 'SET(CMAKE_CXX_COMPILER ' . $cxx . ')';
        }
        $toolchain = <<<CMAKE
{$ccLine}
{$cxxLine}
SET(CMAKE_C_FLAGS "{$cflags}")
SET(CMAKE_CXX_FLAGS "{$cflags}")
SET(CMAKE_FIND_ROOT_PATH "{$root}")
SET(CMAKE_PREFIX_PATH "{$root}")
SET(CMAKE_INSTALL_PREFIX "{$root}")
SET(CMAKE_INSTALL_LIBDIR "lib")

set(PKG_CONFIG_EXECUTABLE "{$pkgConfigExecutable}")
set(PKG_CONFIG_ARGN "--static" CACHE STRING "Extra arguments for pkg-config" FORCE)
set(CMAKE_FIND_ROOT_PATH_MODE_PROGRAM NEVER)
set(CMAKE_FIND_ROOT_PATH_MODE_LIBRARY ONLY)
set(CMAKE_FIND_ROOT_PATH_MODE_INCLUDE ONLY)
set(CMAKE_FIND_ROOT_PATH_MODE_PACKAGE ONLY)
set(CMAKE_EXE_LINKER_FLAGS "-ldl -lpthread -lm -lutil")
CMAKE;
        // Whoops, linux may need CMAKE_AR sometimes
        if (PHP_OS_FAMILY === 'Linux') {
            $toolchain .= "\nSET(CMAKE_AR \"ar\")";
        }
        FileSystem::writeFile(SOURCE_PATH . '/toolchain.cmake', $toolchain);
        return $created = realpath(SOURCE_PATH . '/toolchain.cmake');
    }

    private function initShell(): void
    {
        $this->shell = shell()->initializeEnv($this->library);
    }
}
