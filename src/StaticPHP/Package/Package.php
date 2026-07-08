<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\Artifact\Artifact;
use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\SPCException;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Registry\ArtifactLoader;
use StaticPHP\Registry\PackageLoader;

abstract class Package
{
    use PackageCallbacksTrait;

    /**
     * @var array<string, callable> $stages Defined stages for the package
     */
    protected array $stages = [];

    /** @var array<string, callable> $build_functions Build functions for different OS binding */
    protected array $build_functions = [];

    /** @var array<string, string> */
    protected array $outputs = [];

    /**
     * @param string $name Name of the package
     * @param string $type Type of the package
     */
    public function __construct(public readonly string $name, public readonly string $type) {}

    /**
     * Run a defined stage of the package.
     * If the stage is not defined, an exception should be thrown.
     *
     * @param  array|callable|string $name    Name of the stage to run (can be callable)
     * @param  array                 $context Additional context to pass to the stage callback
     * @return mixed                 Based on the stage definition, return the result of the stage
     */
    public function runStage(mixed $name, array $context = []): mixed
    {
        if (!$this->hasStage($name)) {
            $name = match (true) {
                is_string($name) => $name,
                is_array($name) && count($name) === 2 => $name[1], // use function name
                default => '{' . gettype($name) . '}',
            };
            throw new SPCInternalException("Stage '{$name}' is not defined for package '{$this->name}'.");
        }
        $name = match (true) {
            is_string($name) => $name,
            is_array($name) && count($name) === 2 => $name[1], // use function name
            default => throw new SPCInternalException('Invalid stage name type: ' . gettype($name)),
        };

        // Merge package context with provided context
        /** @noinspection PhpDuplicateArrayKeysInspection */
        $stageContext = array_merge([
            Package::class => $this,
            static::class => $this,
        ], $context);

        try {
            // emit BeforeStage
            $this->emitBeforeStage($name, $stageContext);

            $ret = ApplicationContext::invoke($this->stages[$name], $stageContext);
            // emit AfterStage
            $this->emitAfterStage($name, $stageContext, $ret);
            return $ret;
        } catch (SPCException $e) {
            // Bind package information only if not already bound
            if ($e->getPackageInfo() === null) {
                $e->bindPackageInfo([
                    'package_name' => $this->name,
                    'package_type' => $this->type,
                    'package_class' => static::class,
                    'file' => null,
                    'line' => null,
                ]);
            }
            // Always add current stage to the stack to build call chain
            $e->addStageToStack($name, $stageContext);
            throw $e;
        }
    }

    public function setOutput(string $key, string $value): static
    {
        $this->outputs[$key] = $value;
        return $this;
    }

    public function getOutputs(): array
    {
        return $this->outputs;
    }

    /**
     * Add a build function for a specific platform.
     *
     * @param string   $os_family PHP_OS_FAMILY
     * @param callable $func      Function to build for the platform
     */
    public function addBuildFunction(string $os_family, callable $func): void
    {
        $this->build_functions[$os_family] = $func;
        if ($os_family === PHP_OS_FAMILY) {
            $this->addStage('build', $func);
        }
    }

    public function isInstalled(): bool
    {
        // By default, assume package is not installed.
        return false;
    }

    /**
     * Get the target directory where this package's artifacts should be placed.
     *
     * Libraries install to BUILD_ROOT_PATH (static-libs, headers, pkg-configs).
     * Tools install to PKG_ROOT_PATH (executables).
     * Extensions install to php-src/ext/ (shared objects).
     *
     * Override in subclasses to change the default.
     */
    public function getInstallTarget(): string
    {
        return BUILD_ROOT_PATH;
    }

    /**
     * Add a stage to the package.
     */
    public function addStage(string $name, callable $stage): void
    {
        $this->stages[$name] = $stage;
    }

    /**
     * Get all defined stages for this package.
     *
     * @return array<string, callable>
     */
    public function getStages(): array
    {
        return $this->stages;
    }

    /**
     * Get the list of OS families that have a registered build function (via #[BuildFor]).
     *
     * @return string[] e.g. ['Linux', 'Darwin']
     */
    public function getBuildForOSList(): array
    {
        return array_keys($this->build_functions);
    }

    /**
     * Check if the package has a specific stage defined.
     *
     * @param mixed $name Stage name
     */
    public function hasStage(mixed $name): bool
    {
        if (is_array($name) && count($name) === 2) {
            return isset($this->stages[$name[1]]); // use function name
        }
        if (is_string($name)) {
            return isset($this->stages[$name]); // use defined name
        }
        return false;
    }

    /**
     * Check if the package has a build function for the current OS.
     */
    public function hasBuildFunctionForCurrentOS(): bool
    {
        return isset($this->build_functions[PHP_OS_FAMILY]);
    }

    /**
     * Get the PackageBuilder instance for this package.
     */
    public function getBuilder(): PackageBuilder
    {
        return ApplicationContext::get(PackageBuilder::class);
    }

    /**
     * Get the PackageInstaller instance for this package.
     */
    public function getInstaller(): PackageInstaller
    {
        return ApplicationContext::get(PackageInstaller::class);
    }

    /**
     * Get the name of the package.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the type of the package.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the artifact associated with the package, or null if none is defined.
     *
     * @return null|Artifact Artifact instance or null
     */
    public function getArtifact(): ?Artifact
    {
        // find config
        $artifact_field = PackageConfig::get($this->name, 'artifact');

        if ($artifact_field === null) {
            return null;
        }

        if (is_string($artifact_field)) {
            return ArtifactLoader::getArtifactInstance($artifact_field);
        }

        if (is_array($artifact_field)) {
            return ArtifactLoader::getArtifactInstance($this->name);
        }

        return null;
    }

    /**
     * Check if the artifact has source available.
     */
    public function hasSource(): bool
    {
        return $this->getArtifact()?->hasSource() ?? false;
    }

    /**
     * Get source directory of the package.
     * If the source artifact is not available, an exception will be thrown.
     */
    public function getSourceDir(): string
    {
        if (($artifact = $this->getArtifact()) && $artifact->hasSource()) {
            return $artifact->getSourceDir();
        }
        throw new SPCInternalException("Source directory for package {$this->name} is not available because the source artifact is missing.");
    }

    /**
     * Get source build root directory.
     * It's only worked when 'source-root' is defined in artifact config.
     * Normally it's equal to source dir.
     */
    public function getSourceRoot(): string
    {
        if (($artifact = $this->getArtifact()) && $artifact->hasSource()) {
            return $artifact->getSourceRoot();
        }
        throw new SPCInternalException("Source root for package {$this->name} is not available because the source artifact is missing.");
    }

    /**
     * Check if the package has a binary available for current OS and architecture.
     */
    public function hasLocalBinary(): bool
    {
        return $this->getArtifact()?->hasPlatformBinary() ?? false;
    }

    /**
     * Get extra CFLAGS for current package.
     * You need to define the environment variable in the format of {PACKAGE_NAME}_CFLAGS
     * where {PACKAGE_NAME} is the snake_case name of the package.
     * For example, for libjpeg, the environment variable should be libjpeg_CFLAGS.
     */
    public function getLibExtraCFlags(): string
    {
        // get environment variable
        $env = getenv($this->getSnakeCaseName() . '_CFLAGS') ?: '';
        // get default c flags
        $arch_c_flags = getenv('SPC_DEFAULT_CFLAGS') ?: '';
        if (!empty(getenv('SPC_DEFAULT_CFLAGS')) && !str_contains($env, $arch_c_flags)) {
            $env .= ' ' . $arch_c_flags;
        }
        return trim($env);
    }

    /**
     * Get extra CXXFLAGS for current package.
     * You need to define the environment variable in the format of {PACKAGE_NAME}_CXXFLAGS
     * where {PACKAGE_NAME} is the snake_case name of the package.
     * For example, for libjpeg, the environment variable should be libjpeg_CXXFLAGS.
     */
    public function getLibExtraCxxFlags(): string
    {
        // get environment variable
        $env = getenv($this->getSnakeCaseName() . '_CXXFLAGS') ?: '';
        // get default cxx flags
        $arch_cxx_flags = getenv('SPC_DEFAULT_CXXFLAGS') ?: '';
        if (!empty(getenv('SPC_DEFAULT_CXXFLAGS')) && !str_contains($env, $arch_cxx_flags)) {
            $env .= ' ' . $arch_cxx_flags;
        }
        return trim($env);
    }

    /**
     * Get extra LDFLAGS for current package.
     * You need to define the environment variable in the format of {PACKAGE_NAME}_LDFLAGS
     * where {PACKAGE_NAME} is the snake_case name of the package.
     * For example, for libjpeg, the environment variable should be libjpeg_LDFLAGS.
     */
    public function getLibExtraLdFlags(): string
    {
        // get environment variable
        $env = getenv($this->getSnakeCaseName() . '_LDFLAGS') ?: '';
        // get default ld flags
        $arch_ld_flags = getenv('SPC_DEFAULT_LDFLAGS') ?: '';
        if (!empty(getenv('SPC_DEFAULT_LDFLAGS')) && !str_contains($env, $arch_ld_flags)) {
            $env .= ' ' . $arch_ld_flags;
        }
        return trim($env);
    }

    /**
     * Get extra LIBS for current package.
     * You need to define the environment variable in the format of {PACKAGE_NAME}_LIBS
     * where {PACKAGE_NAME} is the snake_case name of the package.
     * For example, for libjpeg, the environment variable should be libjpeg_LIBS.
     */
    public function getLibExtraLibs(): string
    {
        return getenv($this->getSnakeCaseName() . '_LIBS') ?: '';
    }

    /**
     * Get the snake_case name of the package.
     */
    protected function getSnakeCaseName(): string
    {
        return str_replace('-', '_', $this->name);
    }

    private function emitBeforeStage(string $stage, array $stageContext): void
    {
        foreach (PackageLoader::getBeforeStageCallbacks($this->getName(), $stage) as $callback) {
            ApplicationContext::invoke($callback, $stageContext);
        }
    }

    private function emitAfterStage(string $stage, array $stageContext, mixed $return_value): void
    {
        foreach (PackageLoader::getAfterStageCallbacks($this->getName(), $stage) as $callback) {
            ApplicationContext::invoke($callback, array_merge($stageContext, ['return_value' => $return_value]));
        }
    }
}
