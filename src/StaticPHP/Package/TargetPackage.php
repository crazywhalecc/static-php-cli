<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\DI\ApplicationContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Represents a target package in the StaticPHP package management system.
 */
class TargetPackage extends LibraryPackage
{
    /**
     * @var array<string, InputOption> $build_options Build options for the target package
     */
    protected array $build_options = [];

    protected array $build_arguments = [];

    protected mixed $resolve_build_callback = null;

    /**
     * Checks if the target is virtual.
     */
    public function isVirtualTarget(): bool
    {
        return $this->type === 'virtual-target';
    }

    /**
     * Adds a build option to the target package.
     *
     * @param string      $name        The name of the build option
     * @param null|string $shortcut    The shortcut for the build option
     * @param null|int    $mode        The mode of the build option
     * @param string      $description The description of the build option
     * @param null|mixed  $default     The default value of the build option
     */
    public function addBuildOption(string $name, ?string $shortcut = null, ?int $mode = InputOption::VALUE_NONE, string $description = '', mixed $default = null): void
    {
        $this->build_options[$name] = new InputOption($name, $shortcut, $mode, $description, $default);
    }

    /**
     * Adds a build argument to the target package.
     *
     * @param string     $name        The name of the build argument
     * @param null|int   $mode        The mode of the build argument
     * @param string     $description The description of the build argument
     * @param null|mixed $default     The default value of the build argument
     */
    public function addBuildArgument(string $name, ?int $mode = null, string $description = '', mixed $default = null): void
    {
        $this->build_arguments[$name] = new InputArgument($name, $mode, $description, $default);
    }

    public function setResolveBuildCallback(callable $callback): static
    {
        $this->resolve_build_callback = $callback;
        return $this;
    }

    /**
     * Get a build option value for the target package.
     *
     * @param  string     $key     The build option key
     * @param  null|mixed $default The default value if the option is not set
     * @return mixed      The value of the build option
     */
    public function getBuildOption(string $key, mixed $default = null): mixed
    {
        $input = ApplicationContext::has(InputInterface::class)
            ? ApplicationContext::get(InputInterface::class)
            : null;

        if ($input !== null && $input->hasOption($key)) {
            return $input->getOption($key);
        }

        // try builder options
        $builder = ApplicationContext::has(PackageBuilder::class)
            ? ApplicationContext::get(PackageBuilder::class)
            : null;
        if ($builder !== null && ($option = $builder->getOption($key)) !== null) {
            return $option;
        }
        return $default;
    }

    /**
     * Get a build argument value for the target package.
     *
     * @param  string $key The build argument key
     * @return mixed  The value of the build argument
     */
    public function getBuildArgument(string $key): mixed
    {
        $input = ApplicationContext::has(InputInterface::class)
            ? ApplicationContext::get(InputInterface::class)
            : null;

        if ($input !== null && $input->hasArgument($key)) {
            return $input->getArgument($key);
        }

        // fallback to builder arguments (set programmatically, e.g. from CraftCommand)
        $builder = ApplicationContext::has(PackageBuilder::class)
            ? ApplicationContext::get(PackageBuilder::class)
            : null;
        if ($builder !== null && ($arg = $builder->getArgument($key)) !== null) {
            return $arg;
        }
        return null;
    }

    /**
     * Get target-specific structured data for the build manifest.
     *
     * Target implementations may override this method to expose build facts that
     * cannot be represented by the generic resolved package list.
     *
     * @return array<string, mixed>
     */
    public function getBuildManifestData(PackageInstaller $installer): array
    {
        return [];
    }

    /**
     * Gets all build options for the target package.
     *
     * @internal
     * @return InputOption[] Get all build options for the target package
     */
    public function _exportBuildOptions(): array
    {
        return $this->build_options;
    }

    /**
     * Gets all build arguments for the target package.
     *
     * @internal
     * @return InputArgument[] Get all build arguments for the target package
     */
    public function _exportBuildArguments(): array
    {
        return $this->build_arguments;
    }

    /**
     * Run the init build callback to prepare its dependencies.
     *
     * @internal
     */
    public function _emitResolveBuild(PackageInstaller $installer): mixed
    {
        if (!is_callable($this->resolve_build_callback)) {
            return null;
        }

        return ApplicationContext::invoke($this->resolve_build_callback, [
            TargetPackage::class => $this,
            PackageInstaller::class => $installer,
        ]);
    }
}
