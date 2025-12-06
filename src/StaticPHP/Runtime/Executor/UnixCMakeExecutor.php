<?php

declare(strict_types=1);

namespace StaticPHP\Runtime\Executor;

use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\SPCException;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Runtime\Shell\UnixShell;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use StaticPHP\Util\PkgConfigUtil;
use ZM\Logger\ConsoleColor;

/**
 * Unix-like OS cmake command executor.
 */
class UnixCMakeExecutor extends Executor
{
    protected UnixShell $shell;

    protected array $configure_args = [];

    protected array $ignore_args = [];

    protected ?string $build_dir = null;

    protected ?array $custom_default_args = null;

    protected int $steps = 3;

    protected bool $reset = true;

    protected PackageInstaller $installer;

    public function __construct(protected LibraryPackage $package, ?PackageInstaller $installer = null)
    {
        parent::__construct($package);
        if ($installer !== null) {
            $this->installer = $installer;
        } elseif (ApplicationContext::has(PackageInstaller::class)) {
            $this->installer = ApplicationContext::get(PackageInstaller::class);
        } else {
            throw new SPCInternalException('PackageInstaller not found in container');
        }
        $this->initShell();

        // judge that this package has artifact.source and defined build stage
        if (!$this->package->hasStage('build')) {
            throw new SPCInternalException("Package {$this->package->getName()} does not have a build stage defined.");
        }
    }

    /**
     * Run cmake configure, build and install.
     *
     * @param string $build_pos Build position relative to build directory
     */
    public function build(string $build_pos = '..'): static
    {
        return $this->seekLogFileOnException(function () use ($build_pos) {
            // set cmake dir
            $this->initBuildDir();

            if ($this->reset) {
                FileSystem::resetDir($this->build_dir);
            }

            $this->shell = $this->shell->cd($this->build_dir);

            // config
            if ($this->steps >= 1) {
                $args = array_merge($this->configure_args, $this->getDefaultCMakeArgs());
                $args = array_diff($args, $this->ignore_args);
                $configure_args = implode(' ', $args);
                InteractiveTerm::setMessage('Building package: ' . ConsoleColor::yellow($this->package->getName()) . ' (cmake configure)');
                $this->shell->exec("cmake {$configure_args} {$build_pos}");
            }

            // make
            if ($this->steps >= 2) {
                $concurrency = ApplicationContext::get(PackageBuilder::class)->concurrency;
                InteractiveTerm::setMessage('Building package: ' . ConsoleColor::yellow($this->package->getName()) . ' (cmake build)');
                $this->shell->exec("cmake --build . -j {$concurrency}");
            }

            // install
            if ($this->steps >= 3) {
                InteractiveTerm::setMessage('Building package: ' . ConsoleColor::yellow($this->package->getName()) . ' (cmake install)');
                $this->shell->exec('make install');
            }

            return $this;
        });
    }

    /**
     * Execute a custom command.
     */
    public function exec(string $cmd): static
    {
        $this->shell->exec($cmd);
        return $this;
    }

    /**
     * Add optional package configuration.
     * This method checks if a package is available and adds the corresponding arguments to the CMake configuration.
     *
     * @param  string          $name       package name to check
     * @param  \Closure|string $true_args  arguments to use if the package is available (allow closure, returns string)
     * @param  string          $false_args arguments to use if the package is not available
     * @return $this
     */
    public function optionalPackage(string $name, \Closure|string $true_args, string $false_args = ''): static
    {
        if ($get = $this->installer->getResolvedPackages()[$name] ?? null) {
            logger()->info("Building package [{$this->package->getName()}] with {$name} support");
            $args = $true_args instanceof \Closure ? $true_args($get) : $true_args;
        } else {
            logger()->info("Building package [{$this->package->getName()}] without {$name} support");
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

    /**
     * Remove some configure args, to bypass the configure option checking for some libs.
     */
    public function removeConfigureArgs(...$args): static
    {
        $this->ignore_args = [...$this->ignore_args, ...$args];
        return $this;
    }

    public function setEnv(array $env): static
    {
        $this->shell->setEnv($env);
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
     * Get configure argument string.
     */
    public function getConfigureArgsString(): string
    {
        return implode(' ', array_merge($this->configure_args, $this->getDefaultCMakeArgs()));
    }

    /**
     * Returns the default CMake args.
     */
    private function getDefaultCMakeArgs(): array
    {
        return $this->custom_default_args ?? [
            '-DCMAKE_BUILD_TYPE=Release',
            "-DCMAKE_INSTALL_PREFIX={$this->package->getBuildRootPath()}",
            '-DCMAKE_INSTALL_BINDIR=bin',
            '-DCMAKE_INSTALL_LIBDIR=lib',
            '-DCMAKE_INSTALL_INCLUDEDIR=include',
            '-DPOSITION_INDEPENDENT_CODE=ON',
            '-DBUILD_SHARED_LIBS=OFF',
            "-DCMAKE_TOOLCHAIN_FILE={$this->makeCmakeToolchainFile()}",
        ];
    }

    /**
     * Initialize the CMake build directory.
     * If the directory is not set, it defaults to the package's source directory with '/build' appended.
     */
    private function initBuildDir(): void
    {
        if ($this->build_dir === null) {
            $this->build_dir = "{$this->package->getSourceDir()}/build";
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
        $cxx = getenv('CXX');
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

    /**
     * Initialize UnixShell class.
     */
    private function initShell(): void
    {
        $this->shell = shell()->cd($this->package->getSourceDir())->initializeEnv($this->package)->appendEnv([
            'CFLAGS' => "-I{$this->package->getIncludeDir()}",
            'CXXFLAGS' => "-I{$this->package->getIncludeDir()}",
            'LDFLAGS' => "-L{$this->package->getLibDir()}",
        ]);
    }

    /**
     * When an exception occurs, this method will check if the cmake log file exists.
     */
    private function seekLogFileOnException(mixed $callable): static
    {
        try {
            $callable();
            return $this;
        } catch (SPCException $e) {
            $cmake_log = "{$this->build_dir}/CMakeFiles/CMakeError.log";
            if (file_exists($cmake_log)) {
                logger()->debug("CMake error log file found: {$cmake_log}");
                $log_file = "lib.{$this->package->getName()}.cmake-error.log";
                logger()->debug('Saved CMake error log file to: ' . SPC_LOGS_DIR . "/{$log_file}");
                $e->addExtraLogFile("{$this->package->getName()} library CMakeError.log", $log_file);
                copy($cmake_log, SPC_LOGS_DIR . "/{$log_file}");
            }
            $cmake_output = "{$this->build_dir}/CMakeFiles/CMakeOutput.log";
            if (file_exists($cmake_output)) {
                logger()->debug("CMake output log file found: {$cmake_output}");
                $log_file = "lib.{$this->package->getName()}.cmake-output.log";
                logger()->debug('Saved CMake output log file to: ' . SPC_LOGS_DIR . "/{$log_file}");
                $e->addExtraLogFile("{$this->package->getName()} library CMakeOutput.log", $log_file);
                copy($cmake_output, SPC_LOGS_DIR . "/{$log_file}");
            }
            throw $e;
        }
    }
}
