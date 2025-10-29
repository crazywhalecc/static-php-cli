<?php

declare(strict_types=1);

namespace SPC\util\executor;

use SPC\builder\freebsd\library\BSDLibraryBase;
use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\exception\SPCException;
use SPC\util\shell\UnixShell;

class UnixAutoconfExecutor extends Executor
{
    protected UnixShell $shell;

    protected array $configure_args = [];

    protected array $ignore_args = [];

    public function __construct(protected BSDLibraryBase|LinuxLibraryBase|MacOSLibraryBase $library)
    {
        parent::__construct($library);
        $this->initShell();
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
                $shell->exec('make clean');
            }
            $after_env_vars_str = $after_env_vars !== [] ? shell()->setEnv($after_env_vars)->getEnvString() : '';
            $shell->exec("make -j{$this->library->getBuilder()->concurrency} {$target} {$after_env_vars_str}");
            if ($with_install !== false) {
                $shell->exec("make {$with_install}");
            }
            return $shell;
        });
    }

    public function exec(string $cmd): static
    {
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
            "--prefix={$this->library->getBuildRootPath()}",
            '--with-pic',
            '--enable-pic',
        ];
    }

    /**
     * Initialize UnixShell class.
     */
    private function initShell(): void
    {
        $this->shell = shell()->cd($this->library->getSourceDir())->initializeEnv($this->library)->appendEnv([
            'CFLAGS' => "-I{$this->library->getIncludeDir()}",
            'CXXFLAGS' => "-I{$this->library->getIncludeDir()}",
            'LDFLAGS' => "-L{$this->library->getLibDir()}",
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
            if (file_exists("{$this->library->getSourceDir()}/config.log")) {
                logger()->debug("Config log file found: {$this->library->getSourceDir()}/config.log");
                $log_file = "lib.{$this->library->getName()}.console.log";
                logger()->debug('Saved config log file to: ' . SPC_LOGS_DIR . "/{$log_file}");
                $e->addExtraLogFile("{$this->library->getName()} library config.log", $log_file);
                copy("{$this->library->getSourceDir()}/config.log", SPC_LOGS_DIR . "/{$log_file}");
            }
            throw $e;
        }
    }
}
