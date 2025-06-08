<?php

declare(strict_types=1);

namespace SPC\builder\unix\executor;

use SPC\exception\FileSystemException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;

class UnixCMakeExecutor extends Executor
{
    /** @var null|string CMake build dir */
    protected ?string $cmake_build_dir = null;

    /** @var array CMake additional configure arguments */
    protected array $configure_args = [];

    protected ?array $custom_default_args = null;

    public function build(): void
    {
        // set cmake dir
        $this->initCMakeBuildDir();
        FileSystem::resetDir($this->cmake_build_dir);

        // prepare environment variables
        $env = [
            'CFLAGS' => $this->library->getLibExtraCFlags(),
            'LDFLAGS' => $this->library->getLibExtraLdFlags(),
            'LIBS' => $this->library->getLibExtraLibs(),
        ];

        // prepare shell
        $shell = shell()->cd($this->cmake_build_dir)->setEnv($env);

        // config
        $shell->execWithEnv("cmake {$this->getConfigureArgs()} {$this->getDefaultCMakeArgs()}");

        // make
        $shell->execWithEnv("cmake --build . -j {$this->library->getBuilder()->concurrency}");

        // install
        $shell->execWithEnv('make install');
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
            $args = $true_args instanceof \Closure ? $true_args($get) : $true_args;
        } else {
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
        $this->configure_args = [$this->configure_args, ...$args];
        return $this;
    }

    /**
     * Set custom CMake build directory.
     *
     * @param string $dir custom CMake build directory
     */
    public function setCMakeBuildDir(string $dir): static
    {
        $this->cmake_build_dir = $dir;
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
     * Get configure argument line.
     */
    private function getConfigureArgs(): string
    {
        return implode(' ', $this->configure_args);
    }

    /**
     * @throws WrongUsageException
     * @throws FileSystemException
     */
    private function getDefaultCMakeArgs(): string
    {
        return implode(' ', $this->custom_default_args ?? [
            '-DCMAKE_BUILD_TYPE=Release',
            "-DCMAKE_INSTALL_PREFIX={$this->library->getBuildRootPath()}",
            '-DCMAKE_INSTALL_BINDIR=bin',
            '-DCMAKE_INSTALL_LIBDIR=lib',
            '-DCMAKE_INSTALL_INCLUDE_DIR=include',
            "-DCMAKE_TOOLCHAIN_FILE={$this->makeCmakeToolchainFile()}",
            '..',
        ]);
    }

    /**
     * Initialize the CMake build directory.
     * If the directory is not set, it defaults to the library's source directory with '/build' appended.
     *
     * @throws FileSystemException
     */
    private function initCMakeBuildDir(): void
    {
        if ($this->cmake_build_dir === null) {
            $this->cmake_build_dir = "{$this->library->getSourceDir()}/build";
        }
        FileSystem::resetDir($this->cmake_build_dir);
    }

    /**
     * @return string              CMake toolchain file path
     * @throws FileSystemException
     * @throws WrongUsageException
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

set(PKG_CONFIG_EXECUTABLE "{$root}/bin/pkg-config")
set(CMAKE_FIND_ROOT_PATH_MODE_PROGRAM NEVER)
set(CMAKE_FIND_ROOT_PATH_MODE_LIBRARY ONLY)
set(CMAKE_FIND_ROOT_PATH_MODE_INCLUDE ONLY)
set(CMAKE_FIND_ROOT_PATH_MODE_PACKAGE ONLY)
set(CMAKE_EXE_LINKER_FLAGS "-ldl -lpthread -lm -lutil")
CMAKE;
        // 有时候系统的 cmake 找不到 ar 命令，真奇怪
        if (PHP_OS_FAMILY === 'Linux') {
            $toolchain .= "\nSET(CMAKE_AR \"ar\")";
        }
        FileSystem::writeFile(SOURCE_PATH . '/toolchain.cmake', $toolchain);
        return $created = realpath(SOURCE_PATH . '/toolchain.cmake');
    }
}
