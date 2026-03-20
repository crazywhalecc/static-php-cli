<?php

declare(strict_types=1);

namespace StaticPHP\Runtime\Executor;

use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Runtime\Shell\WindowsCmd;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use StaticPHP\Util\System\WindowsUtil;
use ZM\Logger\ConsoleColor;

class WindowsCMakeExecutor extends Executor
{
    protected WindowsCmd $cmd;

    protected array $configure_args = [];

    protected array $ignore_args = [];

    protected ?string $build_dir = null;

    protected ?array $custom_default_args = null;

    protected int $steps = 2;

    protected bool $reset = true;

    protected PackageBuilder $builder;

    protected PackageInstaller $installer;

    public function __construct(protected LibraryPackage $package)
    {
        parent::__construct($this->package);
        $this->builder = ApplicationContext::get(PackageBuilder::class);
        $this->installer = ApplicationContext::get(PackageInstaller::class);
        $this->initCmd();

        // judge that this package has artifact.source and defined build stage
        if (!$this->package->hasStage('build')) {
            throw new SPCInternalException("Package {$this->package->getName()} does not have a build stage defined.");
        }
    }

    public function build(): static
    {
        $this->initBuildDir();

        if ($this->reset) {
            FileSystem::resetDir($this->build_dir);
        }

        // configure
        if ($this->steps >= 1) {
            $args = array_merge($this->configure_args, $this->getDefaultCMakeArgs());
            $args = array_diff($args, $this->ignore_args);
            $configure_args = implode(' ', $args);
            InteractiveTerm::setMessage('Building package: ' . ConsoleColor::yellow($this->package->getName() . ' (cmake configure)'));
            $this->cmd->exec("cmake {$configure_args}");
        }

        // make
        if ($this->steps >= 2) {
            InteractiveTerm::setMessage('Building package: ' . ConsoleColor::yellow($this->package->getName() . ' (cmake build)'));
            $this->cmd->cd($this->build_dir)->exec("cmake --build {$this->build_dir} --config Release --target install -j{$this->builder->concurrency}");
        }

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
        $this->cmd->setEnv($env);
        return $this;
    }

    public function appendEnv(array $env): static
    {
        $this->cmd->appendEnv($env);
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
            '-A x64',
            '-DCMAKE_BUILD_TYPE=Release',
            '-DBUILD_SHARED_LIBS=OFF',
            '-DBUILD_STATIC_LIBS=ON',
            "-DCMAKE_TOOLCHAIN_FILE={$this->makeCmakeToolchainFile()}",
            '-DCMAKE_INSTALL_PREFIX=' . escapeshellarg($this->package->getBuildRootPath()),
            '-B ' . escapeshellarg(FileSystem::convertPath($this->build_dir)),
        ];
    }

    private function makeCmakeToolchainFile(): string
    {
        if (file_exists(SOURCE_PATH . '\toolchain.cmake')) {
            return SOURCE_PATH . '\toolchain.cmake';
        }
        return WindowsUtil::makeCmakeToolchainFile();
    }

    /**
     * Initialize the CMake build directory.
     * If the directory is not set, it defaults to the package's source directory with '/build' appended.
     */
    private function initBuildDir(): void
    {
        if ($this->build_dir === null) {
            $this->build_dir = "{$this->package->getSourceDir()}\\build";
        }
    }

    private function initCmd(): void
    {
        $this->cmd = cmd()->cd($this->package->getSourceDir());
    }
}
