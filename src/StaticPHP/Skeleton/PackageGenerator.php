<?php

declare(strict_types=1);

namespace StaticPHP\Skeleton;

use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Printer;
use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\Target;
use StaticPHP\Exception\FileSystemException;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Util\FileSystem;

/**
 * A skeleton class for generating package files and configs.
 */
class PackageGenerator
{
    /** @var array<''|'linux'|'macos'|'unix'|'windows', string[]> $depends An array of dependencies required by the package, categorized by operating system. */
    protected array $depends = [];

    /** @var array<''|'linux'|'macos'|'unix'|'windows', string[]> $suggests An array of suggested packages for the package, categorized by operating system. */
    protected array $suggests = [];

    /** @var array<string> $frameworks An array of macOS frameworks for the package */
    protected array $frameworks = [];

    /** @var array<''|'linux'|'macos'|'unix'|'windows', string[]> $static_libs An array of static libraries required by the package, categorized by operating system. */
    protected array $static_libs = [];

    /** @var array<''|'linux'|'macos'|'unix'|'windows', string[]> $headers An array of header files required by the package, categorized by operating system. */
    protected array $headers = [];

    /** @var array<''|'linux'|'macos'|'unix'|'windows', string[]> $static_bins An array of static binaries required by the package, categorized by operating system. */
    protected array $static_bins = [];

    protected ?string $config_file = null;

    /** @var null|ArtifactGenerator $artifact Artifact */
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
     * @param string                                              $package_name Package name
     * @param 'library'|'php-extension'|'target'|'virtual-target' $type         Package type ('library', 'target', 'virtual-target', etc.)
     */
    public function __construct(protected string $package_name, protected string $type) {}

    /**
     * Add package dependency.
     *
     * @param string $package     Package name
     * @param string $os_category Operating system ('' for all OSes, 'unix', 'windows', 'macos')
     */
    public function addDependency(string $package, string $os_category = ''): static
    {
        if (!in_array($os_category, ['', ...SUPPORTED_OS_CATEGORY], true)) {
            throw new ValidationException("Invalid OS suffix: {$os_category}");
        }
        $clone = clone $this;
        if (!isset($clone->depends[$os_category])) {
            $clone->depends[$os_category] = [];
        }
        if (!in_array($package, $clone->depends[$os_category], true)) {
            $clone->depends[$os_category][] = $package;
        }
        return $clone;
    }

    /**
     * Add package suggestion.
     *
     * @param string $package     Package name
     * @param string $os_category Operating system ('' for all OSes)
     */
    public function addSuggestion(string $package, string $os_category = ''): static
    {
        if (!in_array($os_category, ['', ...SUPPORTED_OS_CATEGORY], true)) {
            throw new ValidationException("Invalid OS suffix: {$os_category}");
        }
        $clone = clone $this;
        if (!isset($clone->suggests[$os_category])) {
            $clone->suggests[$os_category] = [];
        }
        if (!in_array($package, $clone->suggests[$os_category], true)) {
            $clone->suggests[$os_category][] = $package;
        }
        return $clone;
    }

    public function addStaticLib(string $lib_a, string $os_category = ''): static
    {
        if (!in_array($os_category, ['', ...SUPPORTED_OS_CATEGORY], true)) {
            throw new ValidationException("Invalid OS suffix: {$os_category}");
        }
        if (!str_ends_with($lib_a, '.lib') && !str_ends_with($lib_a, '.a')) {
            throw new ValidationException("Static library must end with .lib or .a, got: {$lib_a}");
        }
        if (str_ends_with($lib_a, '.lib') && in_array($os_category, ['unix', 'linux', 'macos'], true)) {
            throw new ValidationException("Static library with .lib extension cannot be added for non-Windows OS: {$lib_a}");
        }
        if (str_ends_with($lib_a, '.a') && $os_category === 'windows') {
            throw new ValidationException("Static library with .a extension cannot be added for Windows OS: {$lib_a}");
        }
        if (isset($this->static_libs[$os_category]) && in_array($lib_a, $this->static_libs[$os_category], true)) {
            // already exists
            return $this;
        }
        $clone = clone $this;
        if (!isset($clone->static_libs[$os_category])) {
            $clone->static_libs[$os_category] = [];
        }
        if (!in_array($lib_a, $clone->static_libs[$os_category], true)) {
            $clone->static_libs[$os_category][] = $lib_a;
        }
        return $clone;
    }

    public function addHeader(string $header_file, string $os_category = ''): static
    {
        if (!in_array($os_category, ['', ...SUPPORTED_OS_CATEGORY], true)) {
            throw new ValidationException("Invalid OS suffix: {$os_category}");
        }
        $clone = clone $this;
        if (!isset($clone->headers[$os_category])) {
            $clone->headers[$os_category] = [];
        }
        if (!in_array($header_file, $clone->headers[$os_category], true)) {
            $clone->headers[$os_category][] = $header_file;
        }
        return $clone;
    }

    public function addStaticBin(string $bin_file, string $os_category = ''): static
    {
        if (!in_array($os_category, ['', ...SUPPORTED_OS_CATEGORY], true)) {
            throw new ValidationException("Invalid OS suffix: {$os_category}");
        }
        $clone = clone $this;
        if (!isset($clone->static_bins[$os_category])) {
            $clone->static_bins[$os_category] = [];
        }
        if (!in_array($bin_file, $clone->static_bins[$os_category], true)) {
            $clone->static_bins[$os_category][] = $bin_file;
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
     * @param 'Darwin'|'Linux'|'Windows'|array<'Darwin'|'Linux'|'Windows'> $build_for Build for OS
     */
    public function enableBuild(array|string $build_for, ?string $build_function_name = null): static
    {
        $clone = clone $this;
        if (is_array($build_for)) {
            foreach ($build_for as $bf) {
                $clone = $clone->enableBuild($bf, $build_function_name ?? 'build');
            }
            return $clone;
        }
        if (!in_array($build_for, SUPPORTED_OS_FAMILY, true)) {
            throw new ValidationException("Unsupported build_for value: {$build_for}");
        }
        $clone->build_for_enables[$build_for] = $build_function_name ?? "buildFor{$build_for}";
        return $clone;
    }

    /**
     * Bind function executor.
     *
     * @param string            $func_name Function name
     * @param ExecutorGenerator $executor  Executor generator
     */
    public function addFunctionExecutorBinding(string $func_name, ExecutorGenerator $executor): static
    {
        $clone = clone $this;
        $clone->func_executor_binding[$func_name] = $executor;
        return $clone;
    }

    public function generatePackageClassFile(string $namespace, bool $uppercase = false): string
    {
        $printer = new class extends Printer {
            public string $indentation = '    ';
        };
        $file = new PhpFile();
        $namespace = $file->setStrictTypes()->addNamespace($namespace);

        $uses = [];

        // class name and package attribute
        $class_name = str_replace('-', '_', $uppercase ? ucwords($this->package_name, '-') : $this->package_name);
        $class_attribute = match ($this->type) {
            'library' => Library::class,
            'php-extension' => Extension::class,
            'target', 'virtual-target' => Target::class,
        };
        $package_class = match ($this->type) {
            'library' => LibraryPackage::class,
            'php-extension' => PhpExtensionPackage::class,
            'target', 'virtual-target' => TargetPackage::class,
        };
        $uses[] = $class_attribute;
        $uses[] = $package_class;
        $uses[] = BuildFor::class;
        $uses[] = PackageInstaller::class;

        foreach ($uses as $use) {
            $namespace->addUse($use);
        }

        // add class attribute
        $class = $namespace->addClass($class_name);
        $class->addAttribute($class_attribute, [$this->package_name]);

        // add build functions if enabled
        $funcs = [];
        foreach ($this->build_for_enables as $os_family => $func_name) {
            if ($func_name !== null) {
                $funcs[$func_name][] = $os_family;
            }
        }
        foreach ($funcs as $name => $oss) {
            $method = $class->addMethod(name: $name ?: 'build')
                ->setPublic()
                ->setReturnType('void');
            // check if function executor is bound
            if (isset($this->func_executor_binding[$name])) {
                $executor = $this->func_executor_binding[$name];
                [$executor_use, $code] = $executor->generateCode();
                $namespace->addUse($executor_use);
                $method->setBody($code);
            }
            $method->addParameter('package')->setType($package_class);
            $method->addParameter('installer')->setType(PackageInstaller::class);
            foreach ($oss as $os) {
                $method->addAttribute(BuildFor::class, [$os]);
            }
        }

        return $printer->printFile($file);
    }

    /**
     * Generate package config
     */
    public function generateConfigArray(): array
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

    public function setConfigFile(string $config_file): static
    {
        $clone = clone $this;
        $clone->config_file = $config_file;
        return $clone;
    }

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
        $config_file_json[$this->package_name] = $config_array;
        ksort($config_file_json);
        $json_content = json_encode($config_file_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json_content === false) {
            throw new ValidationException('Failed to encode package config to JSON.');
        }
        if (file_put_contents($this->config_file, $json_content) === false) {
            throw new FileSystemException("Failed to write config file: {$this->config_file}");
        }
        return $this->config_file;
    }

    public function writeAll(): array
    {
        // write config
        $package_config_file = $this->writeConfigFile();
        $artifact_config_file = $this->artifact->writeConfigFile();

        // write class file
        $package_class_file_content = $this->generatePackageClassFile('StaticPHP\Packages');
        $package_class_file_path = str_replace('-', '_', $this->package_name) . '.php';
        // file_put_contents($package_class_file_path, $package_class_file_content); // Uncomment this line to actually write the file
        return [
            'package_config' => $package_config_file,
            'artifact_config' => $artifact_config_file,
            'package_class_file' => $package_class_file_path,
            'package_class_content' => $package_class_file_content,
        ];
    }
}
