<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\Artifact\Artifact;
use StaticPHP\Artifact\ArtifactLoader;
use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\SPCInternalException;

abstract class Package
{
    use PackageCallbacksTrait;

    /**
     * @var array<string, callable> $stages Defined stages for the package
     */
    protected array $stages = [];

    /**
     * @param string $name Name of the package
     * @param string $type Type of the package
     */
    public function __construct(public readonly string $name, public readonly string $type) {}

    /**
     * Run a defined stage of the package.
     * If the stage is not defined, an exception should be thrown.
     *
     * @param  string $name    Name of the stage to run
     * @param  array  $context Additional context to pass to the stage callback
     * @return mixed  Based on the stage definition, return the result of the stage
     */
    public function runStage(string $name, array $context = []): mixed
    {
        if (!isset($this->stages[$name])) {
            throw new SPCInternalException("Stage '{$name}' is not defined for package '{$this->name}'.");
        }

        // Merge package context with provided context
        /** @noinspection PhpDuplicateArrayKeysInspection */
        $stageContext = array_merge([
            Package::class => $this,
            static::class => $this,
        ], $context);

        // emit BeforeStage
        $this->emitBeforeStage($name, $stageContext);

        $ret = ApplicationContext::invoke($this->stages[$name], $stageContext);
        // emit AfterStage
        $this->emitAfterStage($name, $stageContext, $ret);
        return $ret;
    }

    public function isInstalled(): bool
    {
        // By default, assume package is not installed.
        return false;
    }

    /**
     * Add a stage to the package.
     *
     * @param string   $name  Stage name
     * @param callable $stage Stage callable
     */
    public function addStage(string $name, callable $stage): void
    {
        $this->stages[$name] = $stage;
    }

    /**
     * Check if the package has a specific stage defined.
     *
     * @param string $name Stage name
     */
    public function hasStage(string $name): bool
    {
        return isset($this->stages[$name]);
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
        $artifact_name = PackageConfig::get($this->name, 'artifact');
        return $artifact_name !== null ? ArtifactLoader::getArtifactInstance($artifact_name) : null;
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
     * Check if the package has a binary available for current OS and architecture.
     */
    public function hasLocalBinary(): bool
    {
        return $this->getArtifact()?->hasPlatformBinary() ?? false;
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
