<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\SPCConfigUtil;

/**
 * Represents a PHP extension package.
 */
class PhpExtensionPackage extends Package
{
    /**
     * @var array <string, callable> Callbacks for custom PHP configure arguments per OS
     */
    protected array $custom_php_configure_arg_callbacks = [];

    protected bool $build_shared = false;

    protected bool $build_static = false;

    protected bool $build_with_php = false;

    /**
     * @param string $name Name of the php extension
     * @param string $type Type of the package, defaults to 'php-extension'
     */
    public function __construct(string $name, string $type = 'php-extension', protected array $extension_config = [])
    {
        // Ensure the package name starts with 'ext-'
        if (!str_starts_with($name, 'ext-')) {
            $name = "ext-{$name}";
        }
        if ($this->extension_config === []) {
            $this->extension_config = PackageConfig::get($name, 'php-extension', []);
        }
        parent::__construct($name, $type);
    }

    public function getSourceDir(): string
    {
        if ($this->getArtifact() === null) {
            $path = SOURCE_PATH . '/php-src/ext/' . $this->getExtensionName();
            if (!is_dir($path)) {
                throw new ValidationException("Extension source directory not found: {$path}", validation_module: "Extension {$this->getExtensionName()} source");
            }
            return $path;
        }
        return parent::getSourceDir();
    }

    public function getExtensionName(): string
    {
        return str_replace('ext-', '', $this->getName());
    }

    public function addCustomPhpConfigureArgCallback(string $os, callable $fn): void
    {
        if ($os === '') {
            foreach (['Linux', 'Windows', 'Darwin'] as $supported_os) {
                $this->custom_php_configure_arg_callbacks[$supported_os] = $fn;
            }
        } else {
            $this->custom_php_configure_arg_callbacks[$os] = $fn;
        }
    }

    public function getPhpConfigureArg(string $os, bool $shared): string
    {
        if (isset($this->custom_php_configure_arg_callbacks[$os])) {
            $callback = $this->custom_php_configure_arg_callbacks[$os];
            return ApplicationContext::invoke($callback, ['shared' => $shared, static::class => $this, Package::class => $this]);
        }
        $escapedPath = str_replace("'", '', escapeshellarg(BUILD_ROOT_PATH)) !== BUILD_ROOT_PATH || str_contains(BUILD_ROOT_PATH, ' ') ? escapeshellarg(BUILD_ROOT_PATH) : BUILD_ROOT_PATH;
        $name = str_replace('_', '-', $this->getExtensionName());
        $ext_config = PackageConfig::get($name, 'php-extension', []);

        $arg_type = match (SystemTarget::getTargetOS()) {
            'Windows' => $ext_config['arg-type@windows'] ?? $ext_config['arg-type'] ?? 'enable',
            'Darwin' => $ext_config['arg-type@macos'] ?? $ext_config['arg-type@unix'] ?? $ext_config['arg-type'] ?? 'enable',
            'Linux' => $ext_config['arg-type@linux'] ?? $ext_config['arg-type@unix'] ?? $ext_config['arg-type'] ?? 'enable',
            default => $ext_config['arg-type'] ?? 'enable',
        };

        return match ($arg_type) {
            'enable' => $shared ? "--enable-{$name}=shared" : "--enable-{$name}",
            'enable-path' => $shared ? "--enable-{$name}=shared,{$escapedPath}" : "--enable-{$name}={$escapedPath}",
            'with' => $shared ? "--with-{$name}=shared" : "--with-{$name}",
            'with-path' => $shared ? "--with-{$name}=shared,{$escapedPath}" : "--with-{$name}={$escapedPath}",
            default => throw new WrongUsageException("Unknown argument type '{$arg_type}' for PHP extension '{$name}'"),
        };
    }

    public function setBuildShared(bool $build_shared = true): void
    {
        $this->build_shared = $build_shared;
        // Add build stages for shared build on Unix-like systems
        // TODO: Windows shared build support
        if ($build_shared && in_array(SystemTarget::getTargetOS(), ['Linux', 'Darwin'])) {
            if (!$this->hasStage('build')) {
                $this->addBuildFunction(SystemTarget::getTargetOS(), [$this, '_buildSharedUnix']);
            }
            if (!$this->hasStage('phpize')) {
                $this->addStage('phpize', [$this, '_phpize']);
            }
            if (!$this->hasStage('configure')) {
                $this->addStage('configure', [$this, '_configure']);
            }
            if (!$this->hasStage('make')) {
                $this->addStage('make', [$this, '_make']);
            }
        }
    }

    public function setBuildStatic(bool $build_static = true): void
    {
        $this->build_static = $build_static;
    }

    public function setBuildWithPhp(bool $build_with_php = true): void
    {
        $this->build_with_php = $build_with_php;
    }

    public function isBuildShared(): bool
    {
        return $this->build_shared;
    }

    public function isBuildStatic(): bool
    {
        return $this->build_static;
    }

    public function isBuildWithPhp(): bool
    {
        return $this->build_with_php;
    }

    public function buildShared(): void
    {
        if ($this->hasStage('build')) {
            $this->runStage('build');
        } else {
            throw new WrongUsageException("Extension [{$this->getExtensionName()}] cannot build shared target yet.");
        }
    }

    /**
     * Get shared extension build environment variables for Unix.
     *
     * @return array{
     *     CFLAGS: string,
     *     CXXFLAGS: string,
     *     LDFLAGS: string,
     *     LIBS: string,
     *     LD_LIBRARY_PATH: string
     * }
     */
    public function getSharedExtensionEnv(): array
    {
        $config = (new SPCConfigUtil())->getExtensionConfig($this);
        [$staticLibs, $sharedLibs] = $this->splitLibsIntoStaticAndShared($config['libs']);
        $preStatic = PHP_OS_FAMILY === 'Darwin' ? '' : '-Wl,--start-group ';
        $postStatic = PHP_OS_FAMILY === 'Darwin' ? '' : ' -Wl,--end-group ';
        return [
            'CFLAGS' => $config['cflags'],
            'CXXFLAGS' => $config['cflags'],
            'LDFLAGS' => $config['ldflags'],
            'LIBS' => clean_spaces("{$preStatic} {$staticLibs} {$postStatic} {$sharedLibs}"),
            'LD_LIBRARY_PATH' => BUILD_LIB_PATH,
        ];
    }

    /**
     * @internal
     * #[Stage('phpize')]
     */
    public function _phpize(array $env, PhpExtensionPackage $package): void
    {
        shell()->cd($package->getSourceDir())->setEnv($env)->exec(BUILD_BIN_PATH . '/phpize');
    }

    /**
     * @internal
     * #[Stage('configure')]
     */
    public function _configure(array $env, PhpExtensionPackage $package): void
    {
        $phpvars = getenv('SPC_EXTRA_PHP_VARS') ?: '';
        shell()->cd($package->getSourceDir())
            ->setEnv($env)
            ->exec(
                './configure ' . $this->getPhpConfigureArg(SystemTarget::getCurrentPlatformString(), true) .
                ' --with-php-config=' . BUILD_BIN_PATH . '/php-config ' .
                "--enable-shared --disable-static {$phpvars}"
            );
    }

    /**
     * @internal
     * #[Stage('make')]
     */
    public function _make(array $env, PhpExtensionPackage $package, PackageBuilder $builder): void
    {
        shell()->cd($package->getSourceDir())
            ->setEnv($env)
            ->exec('make clean')
            ->exec("make -j{$builder->concurrency}")
            ->exec('make install');
    }

    /**
     * Build shared extension on Unix-like systems.
     * Only for internal calling. For external use, call buildShared() instead.
     * @internal
     * #[Stage('build')]
     */
    public function _buildSharedUnix(PackageBuilder $builder): void
    {
        $env = $this->getSharedExtensionEnv();

        $this->runStage('phpize', ['env' => $env]);
        $this->runStage('configure', ['env' => $env]);
        $this->runStage('make', ['env' => $env]);

        // process *.so file
        $soFile = BUILD_MODULES_PATH . '/' . $this->getExtensionName() . '.so';
        if (!file_exists($soFile)) {
            throw new ValidationException("Extension {$this->getExtensionName()} build failed: {$soFile} not found", validation_module: "Extension {$this->getExtensionName()} build");
        }
        $builder->deployBinary($soFile, $soFile, false);
    }

    /**
     * Splits a given string of library flags into static and shared libraries.
     *
     * @param  string $allLibs A space-separated string of library flags (e.g., -lxyz).
     * @return array  an array containing two elements: the first is a space-separated string
     *                of static library flags, and the second is a space-separated string
     *                of shared library flags
     */
    protected function splitLibsIntoStaticAndShared(string $allLibs): array
    {
        $staticLibString = '';
        $sharedLibString = '';
        $libs = explode(' ', $allLibs);
        foreach ($libs as $lib) {
            $staticLib = BUILD_LIB_PATH . '/lib' . str_replace('-l', '', $lib) . '.a';
            if (str_starts_with($lib, BUILD_LIB_PATH . '/lib') && str_ends_with($lib, '.a')) {
                $staticLib = $lib;
            }
            if ($lib === '-lphp' || !file_exists($staticLib)) {
                $sharedLibString .= " {$lib}";
            } else {
                $staticLibString .= " {$lib}";
            }
        }
        return [trim($staticLibString), trim($sharedLibString)];
    }
}
