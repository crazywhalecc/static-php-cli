<?php

namespace StaticPHP\Skeleton;

class ArtifactGenerator
{
    protected ?array $source = null;

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

    public function generateConfig(): array
    {
        $config = [];

        if ($this->source) {
            $config['source'] = $this->source;
        }
        return $config;
    }
}
