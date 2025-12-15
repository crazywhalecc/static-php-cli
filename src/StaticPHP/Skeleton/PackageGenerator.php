<?php

namespace StaticPHP\Skeleton;

use StaticPHP\Exception\ValidationException;

/**
 * A skeleton class for generating package files and configs.
 */
class PackageGenerator
{
    /** @var array<''|'unix'|'windows'|'macos'|'linux', string[]> $depends An array of dependencies required by the package, categorized by operating system. */
    protected array $depends = [];

    /** @var array<''|'unix'|'windows'|'macos'|'linux', string[]> $suggests An array of suggested packages for the package, categorized by operating system. */
    protected array $suggests = [];

    /** @var array<string> $frameworks An array of macOS frameworks for the package */
    protected array $frameworks = [];

    /** @var array<''|'unix'|'windows'|'macos'|'linux', string[]> $static_libs An array of static libraries required by the package, categorized by operating system. */
    protected array $static_libs = [];

    /** @var array<''|'unix'|'windows'|'macos'|'linux', string[]> $headers An array of header files required by the package, categorized by operating system. */
    protected array $headers = [];

    /** @var array<''|'unix'|'windows'|'macos'|'linux', string[]> $static_bins An array of static binaries required by the package, categorized by operating system. */
    protected array $static_bins = [];

    /** @var ArtifactGenerator|null $artifact Artifact */
    protected ?ArtifactGenerator $artifact = null;

    /** @var array $licenses Licenses */
    protected array $licenses = [];

    /** @var array<'Darwin'|'Linux'|'Windows', null|string> $build_for_enables Enable build function generating */
    protected array $build_for_enables = [
        'Darwin' => null,
        'Linux' => null,
        'Windows' => null,
    ];

    /** @var array<string, ExecutorGenerator> */
    protected array $func_executor_binding = [];

    /**
     * @param string $package_name Package name
     * @param 'library'|'target'|'virtual-target'|'php-extension' $type Package type ('library', 'target', 'virtual-target', etc.)
     */
    public function __construct(protected string $package_name, protected string $type) {}

    /**
     * Add package dependency.
     *
     * @param string $package Package name
     * @param string $os Operating system ('' for all OSes, '@unix', '@windows', '@macos')
     */
    public function addDependency(string $package, string $os = ''): static
    {
        if (!in_array($os, ['', 'unix', 'windows', 'macos', 'linux'], true)) {
            throw new ValidationException("Invalid OS suffix: {$os}");
        }
        $clone = clone $this;
        if (!isset($clone->depends[$os])) {
            $clone->depends[$os] = [];
        }
        if (!in_array($package, $clone->depends[$os], true)) {
            $clone->depends[$os][] = $package;
        }
        return $clone;
    }

    /**
     * Add package suggestion.
     *
     * @param string $package Package name
     * @param string $os Operating system ('' for all OSes, '@unix', '@windows', '@macos')
     */
    public function addSuggestion(string $package, string $os = ''): static
    {
        if (!in_array($os, ['', 'unix', 'windows', 'macos', 'linux'], true)) {
            throw new ValidationException("Invalid OS suffix: {$os}");
        }
        $clone = clone $this;
        if (!isset($clone->suggests[$os])) {
            $clone->suggests[$os] = [];
        }
        if (!in_array($package, $clone->suggests[$os], true)) {
            $clone->suggests[$os][] = $package;
        }
        return $clone;
    }

    public function addStaticLib(string $lib_a, string $os = ''): static
    {
        if (!in_array($os, ['', 'unix', 'windows', 'macos', 'linux'], true)) {
            throw new ValidationException("Invalid OS suffix: {$os}");
        }
        if (!str_ends_with($lib_a, '.lib') && !str_ends_with($lib_a, '.a')) {
            throw new ValidationException("Static library must end with .lib or .a, got: {$lib_a}");
        }
        if (str_ends_with($lib_a, '.lib') && in_array($os, ['unix', 'linux', 'macos'], true)) {
            throw new ValidationException("Static library with .lib extension cannot be added for non-Windows OS: {$lib_a}");
        }
        if (str_ends_with($lib_a, '.a') && $os === 'windows') {
            throw new ValidationException("Static library with .a extension cannot be added for Windows OS: {$lib_a}");
        }
        if (isset($this->static_libs[$os]) && in_array($lib_a, $this->static_libs[$os], true)) {
            // already exists
            return $this;
        }
        $clone = clone $this;
        if (!isset($clone->static_libs[$os])) {
            $clone->static_libs[$os] = [];
        }
        if (!in_array($lib_a, $clone->static_libs[$os], true)) {
            $clone->static_libs[$os][] = $lib_a;
        }
        return $clone;
    }

    public function addHeader(string $header_file, string $os = ''): static
    {
        if (!in_array($os, ['', 'unix', 'windows', 'macos', 'linux'], true)) {
            throw new ValidationException("Invalid OS suffix: {$os}");
        }
        $clone = clone $this;
        if (!isset($clone->headers[$os])) {
            $clone->headers[$os] = [];
        }
        if (!in_array($header_file, $clone->headers[$os], true)) {
            $clone->headers[$os][] = $header_file;
        }
        return $clone;
    }

    public function addStaticBin(string $bin_file, string $os = ''): static
    {
        if (!in_array($os, ['', 'unix', 'windows', 'macos', 'linux'], true)) {
            throw new ValidationException("Invalid OS suffix: {$os}");
        }
        $clone = clone $this;
        if (!isset($clone->static_bins[$os])) {
            $clone->static_bins[$os] = [];
        }
        if (!in_array($bin_file, $clone->static_bins[$os], true)) {
            $clone->static_bins[$os][] = $bin_file;
        }
        return $clone;
    }

    /**
     * Add package artifact.
     *
     * @param ArtifactGenerator $artifactGenerator Artifact generator
     */
    public function addArtifact(ArtifactGenerator $artifactGenerator): static
    {
        $clone = clone $this;
        $clone->artifact = $artifactGenerator;
        return $clone;
    }

    /**
     * Add license from string.
     *
     * @param string $text License content
     */
    public function addLicenseFromString(string $text): static
    {
        $clone = clone $this;
        $clone->licenses[] = [
            'type' => 'text',
            'text' => $text,
        ];
        return $clone;
    }

    /**
     * Add license from file.
     *
     * @param string $file_path License file path
     */
    public function addLicenseFromFile(string $file_path): static
    {
        $clone = clone $this;
        $clone->licenses[] = [
            'type' => 'file',
            'path' => $file_path,
        ];
        return $clone;
    }

    /**
     * Enable build for specific OS.
     *
     * @param 'Windows'|'Linux'|'Darwin'|array<'Windows'|'Linux'|'Darwin'> $build_for Build for OS
     */
    public function enableBuild(string|array $build_for, ?string $build_function_name = null): static
    {
        $clone = clone $this;
        if (is_array($build_for)) {
            foreach ($build_for as $bf) {
                $clone = $clone->enableBuild($bf, $build_function_name ?? 'build');
            }
            return $clone;
        }
        $clone->build_for_enables[$build_for] = $build_function_name ?? "buildFor{$build_for}";
        return $clone;
    }

    /**
     * Bind function executor.
     *
     * @param string $func_name Function name
     * @param ExecutorGenerator $executor Executor generator
     */
    public function addFunctionExecutorBinding(string $func_name, ExecutorGenerator $executor): static
    {
        $clone = clone $this;
        $clone->func_executor_binding[$func_name] = $executor;
        return $clone;
    }

    /**
     * Generate package config
     */
    public function generateConfig(): array
    {
        $config = ['type' => $this->type];

        // Add dependencies
        foreach ($this->depends as $suffix => $depends) {
            $k = $suffix !== '' ? "depends@{$suffix}" : 'depends';
            $config[$k] = $depends;
        }

        // add suggests
        foreach ($this->suggests as $suffix => $suggests) {
            $k = $suffix !== '' ? "suggests@{$suffix}" : 'suggests';
            $config[$k] = $suggests;
        }

        // Add frameworks
        if (!empty($this->frameworks)) {
            $config['frameworks'] = $this->frameworks;
        }

        // Add static libs
        foreach ($this->static_libs as $suffix => $libs) {
            $k = $suffix !== '' ? "static-libs@{$suffix}" : 'static-libs';
            $config[$k] = $libs;
        }

        // Add headers
        foreach ($this->headers as $suffix => $headers) {
            $k = $suffix !== '' ? "headers@{$suffix}" : 'headers';
            $config[$k] = $headers;
        }

        // Add static bins
        foreach ($this->static_bins as $suffix => $bins) {
            $k = $suffix !== '' ? "static-bins@{$suffix}" : 'static-bins';
            $config[$k] = $bins;
        }

        // Add artifact
        if ($this->artifact !== null) {
            $config['artifact'] = $this->artifact->getName();
        }

        // Add licenses
        if (!empty($this->licenses)) {
            if (count($this->licenses) === 1) {
                $config['license'] = $this->licenses[0];
            } else {
                $config['license'] = $this->licenses;
            }
        }

        return $config;
    }
}
