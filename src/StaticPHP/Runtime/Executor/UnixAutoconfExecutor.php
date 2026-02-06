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
use StaticPHP\Util\InteractiveTerm;
use ZM\Logger\ConsoleColor;

class UnixAutoconfExecutor extends Executor
{
    protected UnixShell $shell;

    protected array $configure_args = [];

    protected array $ignore_args = [];

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
     * Run ./configure
     */
    public function configure(...$args): static
    {
        // remove all the ignored args
        $args = array_merge($args, $this->getDefaultConfigureArgs(), $this->configure_args);
        $args = array_diff($args, $this->ignore_args);
        $configure_args = implode(' ', $args);
        InteractiveTerm::setMessage('Building package: ' . ConsoleColor::yellow($this->package->getName()) . ' (./configure)');
        return $this->seekLogFileOnException(fn () => $this->shell->exec("./configure {$configure_args}"));
    }

    public function getConfigureArgsString(): string
    {
        return implode(' ', array_merge($this->getDefaultConfigureArgs(), $this->configure_args));
    }

    /**
     * Run make
     *
     * @param string       $target         Build target
     * @param false|string $with_install   Run `make install` after building, or false to skip
     * @param bool         $with_clean     Whether to clean before building
     * @param array        $after_env_vars Environment variables postfix
     */
    public function make(string $target = '', false|string $with_install = 'install', bool $with_clean = true, array $after_env_vars = [], ?string $dir = null): static
    {
        return $this->seekLogFileOnException(function () use ($target, $with_install, $with_clean, $after_env_vars, $dir) {
            $shell = $this->shell;
            if ($dir) {
                $shell = $shell->cd($dir);
            }
            if ($with_clean) {
                InteractiveTerm::setMessage('Building package: ' . ConsoleColor::yellow($this->package->getName()) . ' (make clean)');
                $shell->exec('make clean');
            }
            $after_env_vars_str = $after_env_vars !== [] ? shell()->setEnv($after_env_vars)->getEnvString() : '';
            $concurrency = ApplicationContext::get(PackageBuilder::class)->concurrency;
            InteractiveTerm::setMessage('Building package: ' . ConsoleColor::yellow($this->package->getName()) . ' (make)');
            $shell->exec("make -j{$concurrency} {$target} {$after_env_vars_str}");
            if ($with_install !== false) {
                InteractiveTerm::setMessage('Building package: ' . ConsoleColor::yellow($this->package->getName()) . ' (make ' . $with_install . ')');
                $shell->exec("make {$with_install}");
            }
            return $shell;
        });
    }

    public function exec(string $cmd): static
    {
        InteractiveTerm::setMessage('Building package: ' . ConsoleColor::yellow($this->package->getName()));
        $this->shell->exec($cmd);
        return $this;
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
     * Returns the default autoconf ./configure arguments
     */
    private function getDefaultConfigureArgs(): array
    {
        return [
            '--disable-shared',
            '--enable-static',
            "--prefix={$this->package->getBuildRootPath()}",
            '--with-pic',
            '--enable-pic',
        ];
    }

    /**
     * Initialize UnixShell class.
     */
    private function initShell(): void
    {
        $this->shell = shell()->cd($this->package->getSourceRoot())->initializeEnv($this->package)->appendEnv([
            'CFLAGS' => "-I{$this->package->getIncludeDir()}",
            'CXXFLAGS' => "-I{$this->package->getIncludeDir()}",
            'LDFLAGS' => "-L{$this->package->getLibDir()}",
        ]);
    }

    /**
     * When an exception occurs, this method will check if the config log file exists.
     */
    private function seekLogFileOnException(mixed $callable): static
    {
        try {
            $callable();
            return $this;
        } catch (SPCException $e) {
            if (file_exists("{$this->package->getSourceRoot()}/config.log")) {
                logger()->debug("Config log file found: {$this->package->getSourceRoot()}/config.log");
                $log_file = "lib.{$this->package->getName()}.console.log";
                logger()->debug('Saved config log file to: ' . SPC_LOGS_DIR . "/{$log_file}");
                $e->addExtraLogFile("{$this->package->getName()} library config.log", $log_file);
                copy("{$this->package->getSourceRoot()}/config.log", SPC_LOGS_DIR . "/{$log_file}");
            }
            throw $e;
        }
    }
}
