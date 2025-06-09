<?php

declare(strict_types=1);

namespace SPC\util\executor;

use SPC\builder\freebsd\library\BSDLibraryBase;
use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\exception\RuntimeException;
use SPC\util\UnixShell;

class UnixAutoconfExecutor extends Executor
{
    protected ?UnixShell $shell = null;

    protected array $configure_args = [];

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
        $configure_args = implode(' ', array_merge($args, $this->getDefaultConfigureArgs(), $this->configure_args));

        $this->shell->exec("./configure {$configure_args}");
        return $this;
    }

    public function getConfigureArgsString(): string
    {
        return implode(' ', array_merge($this->getDefaultConfigureArgs(), $this->configure_args));
    }

    /**
     * Run make
     *
     * @param  string           $target Build target
     * @throws RuntimeException
     */
    public function make(string $target = '', false|string $with_install = 'install', bool $with_clean = true, array $after_env_vars = []): static
    {
        if ($with_clean) {
            $this->shell->exec('make clean');
        }
        $after_env_vars_str = $after_env_vars !== [] ? shell()->setEnv($after_env_vars)->getEnvString() : '';
        $this->shell->exec("make -j{$this->library->getBuilder()->concurrency} {$target} {$after_env_vars_str}");
        if ($with_install !== false) {
            $this->shell->exec("make {$with_install}");
        }
        return $this;
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
        $this->configure_args = [...$this->configure_args, ...$args];
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
        ];
    }

    /**
     * Initialize UnixShell class.
     */
    private function initShell(): void
    {
        $this->shell = shell()->cd($this->library->getSourceDir())->initializeEnv($this->library)->appendEnv([
            'CFLAGS' => "-I{$this->library->getIncludeDir()}",
            'LDFLAGS' => "-L{$this->library->getLibDir()}",
        ]);
    }
}
