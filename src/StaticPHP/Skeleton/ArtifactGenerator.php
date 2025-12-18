<?php

declare(strict_types=1);

namespace StaticPHP\Skeleton;

use StaticPHP\Exception\FileSystemException;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Util\FileSystem;

class ArtifactGenerator
{
    protected ?array $source = null;

    protected ?array $binary = null;

    protected ?string $config_file = null;

    protected bool $generate_class = false;

    protected bool $generate_custom_source_func = false;

    protected bool $generate_custom_binary_func_for_unix = false;

    protected bool $generate_custom_binary_func_for_windows = false;

    public function __construct(protected string $name) {}

    /**
     * Get the artifact name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function setSource(array $source): static
    {
        $clone = clone $this;
        $clone->source = $source;
        return $clone;
    }

    public function setCustomSource(): static
    {
        $clone = clone $this;
        $clone->source = ['type' => 'custom'];
        $clone->generate_class = true;
        $clone->generate_custom_source_func = true;
        return $clone;
    }

    public function getSource(): ?array
    {
        return $this->source;
    }

    public function setBinary(string $os, array $config): static
    {
        $clone = clone $this;
        if ($clone->binary === null) {
            $clone->binary = [$os => $config];
        } else {
            $clone->binary[$os] = $config;
        }
        return $clone;
    }

    public function generateConfigArray(): array
    {
        $config = [];

        if ($this->source) {
            $config['source'] = $this->source;
        }
        if ($this->binary) {
            $config['binary'] = $this->binary;
        }
        return $config;
    }

    public function setConfigFile(string $file): static
    {
        $clone = clone $this;
        $clone->config_file = $file;
        return $clone;
    }

    /**
     * Write the artifact configuration to the config file.
     */
    public function writeConfigFile(): string
    {
        if ($this->config_file === null) {
            throw new ValidationException('Config file path is not set.');
        }
        $config_array = $this->generateConfigArray();
        $config_file_json = json_decode(FileSystem::readFile($this->config_file), true);
        if (!is_array($config_file_json)) {
            throw new ValidationException('Existing config file is not a valid JSON array.');
        }

        $config_file_json[$this->name] = $config_array;
        // sort keys
        ksort($config_file_json);
        $json_content = json_encode($config_file_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json_content === false) {
            throw new ValidationException('Failed to encode config array to JSON.');
        }
        if (file_put_contents($this->config_file, $json_content) === false) {
            throw new FileSystemException("Failed to write config file: {$this->config_file}");
        }
        return $this->config_file;
    }
}
