<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\Attribute\Package\Stage;
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

        $arg = match ($arg_type) {
            'enable' => $shared ? "--enable-{$name}=shared" : "--enable-{$name}",
            'enable-path' => $shared ? "--enable-{$name}=shared,{$escapedPath}" : "--enable-{$name}={$escapedPath}",
            'with' => $shared ? "--with-{$name}=shared" : "--with-{$name}",
            'with-path' => $shared ? "--with-{$name}=shared,{$escapedPath}" : "--with-{$name}={$escapedPath}",
            'custom' => '',
            default => $arg_type,
        };
        // customize argument from config string
        $replace = get_pack_replace();
        $arg = str_replace(array_values($replace), array_keys($replace), $arg);
        $replace = [
            '@shared_suffix@' => $shared ? '=shared' : '',
            '@shared_path_suffix@' => $shared ? "=shared,{$escapedPath}" : "={$escapedPath}",
        ];
        return str_replace(array_keys($replace), array_values($replace), $arg);
    }

    public function setBuildShared(bool $build_shared = true): void
    {
        $this->build_shared = $build_shared;
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
     * Get the dist name used for `--ri` check in smoke test.
     * Reads from config `dist-name` field, defaults to extension name.
     */
    public function getDistName(): string
    {
        return $this->extension_config['dist-name'] ?? $this->getExtensionName();
    }

    /**
     * Run smoke test for the extension on Unix CLI.
     * Override this method in a subclass.
     */
    public function runSmokeTestCliUnix(): void
    {
        if (($this->extension_config['smoke-test'] ?? true) === false) {
            return;
        }

        $distName = $this->getDistName();
        // empty dist-name → no --ri check (e.g. password_argon2)
        if ($distName === '') {
            return;
        }

        $sharedExtensions = $this->getSharedExtensionLoadString();
        [$ret] = shell()->execWithResult(BUILD_BIN_PATH . '/php -n' . $sharedExtensions . ' --ri "' . $distName . '"', false);
        if ($ret !== 0) {
            throw new ValidationException(
                "extension {$this->getName()} failed compile check: php-cli returned {$ret}",
                validation_module: 'Extension ' . $this->getName() . ' sanity check'
            );
        }

        $test_file = ROOT_DIR . '/src/globals/ext-tests/' . $this->getExtensionName() . '.php';
        if (file_exists($test_file)) {
            // Trim additional content & escape special characters to allow inline usage
            $test = self::escapeInlineTest(file_get_contents($test_file));
            [$ret, $out] = shell()->execWithResult(BUILD_BIN_PATH . '/php -n' . $sharedExtensions . ' -r "' . trim($test) . '"');
            if ($ret !== 0) {
                throw new ValidationException(
                    "extension {$this->getName()} failed sanity check. Code: {$ret}, output: " . implode("\n", $out),
                    validation_module: 'Extension ' . $this->getName() . ' function check'
                );
            }
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
     */
    #[Stage]
    public function phpizeForUnix(array $env, PhpExtensionPackage $package): void
    {
        shell()->cd($package->getSourceDir())->setEnv($env)->exec(BUILD_BIN_PATH . '/phpize');
    }

    /**
     * @internal
     */
    #[Stage]
    public function configureForUnix(array $env, PhpExtensionPackage $package): void
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
     */
    #[Stage]
    public function makeForUnix(array $env, PhpExtensionPackage $package, PackageBuilder $builder): void
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
    public function buildSharedForUnix(PackageBuilder $builder): void
    {
        $env = $this->getSharedExtensionEnv();

        $this->runStage([$this, 'phpizeForUnix'], ['env' => $env]);
        $this->runStage([$this, 'configureForUnix'], ['env' => $env]);
        $this->runStage([$this, 'makeForUnix'], ['env' => $env]);

        // process *.so file
        $soFile = BUILD_MODULES_PATH . '/' . $this->getExtensionName() . '.so';
        if (!file_exists($soFile)) {
            throw new ValidationException("Extension {$this->getExtensionName()} build failed: {$soFile} not found", validation_module: "Extension {$this->getExtensionName()} build");
        }
        $builder->deployBinary($soFile, $soFile, false);
    }

    /**
     * Get per-OS build support status for this php-extension.
     *
     * Rules (same as v2):
     * - OS not listed in 'support' config  => 'yes' (fully supported)
     * - OS listed with 'wip'               => 'wip'
     * - OS listed with 'partial'           => 'partial'
     * - OS listed with 'no'               => 'no'
     *
     * @return array<string, string> e.g. ['Linux' => 'yes', 'Darwin' => 'partial', 'Windows' => 'no']
     */
    public function getBuildSupportStatus(): array
    {
        $exceptions = $this->extension_config['support'] ?? [];
        $result = [];
        foreach (['Linux', 'Darwin', 'Windows'] as $os) {
            $result[$os] = $exceptions[$os] ?? 'yes';
        }
        return $result;
    }

    /**
     * Register default stages if not already defined by attributes.
     * This is called after all attributes have been loaded.
     *
     * @internal Called by PackageLoader after loading attributes
     */
    public function registerDefaultStages(): void
    {
        // Add build stages for shared build on Unix-like systems
        // TODO: Windows shared build support
        if ((PackageConfig::get($this->getName(), 'php-extension')['build-shared'] ?? true) && in_array(SystemTarget::getTargetOS(), ['Linux', 'Darwin'])) {
            if (!$this->hasStage('build')) {
                $this->addBuildFunction(SystemTarget::getTargetOS(), [$this, 'buildSharedForUnix']);
            }
            if (!$this->hasStage('phpizeForUnix')) {
                $this->addStage('phpizeForUnix', [$this, 'phpizeForUnix']);
            }
            if (!$this->hasStage('configureForUnix')) {
                $this->addStage('configureForUnix', [$this, 'configureForUnix']);
            }
            if (!$this->hasStage('makeForUnix')) {
                $this->addStage('makeForUnix', [$this, 'makeForUnix']);
            }
        }
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

    /**
     * Builds the `-d extension_dir=... -d extension=...` string for all resolved shared extensions.
     * Used in CLI smoke test to load shared extension dependencies at runtime.
     */
    private function getSharedExtensionLoadString(): string
    {
        $sharedExts = array_filter(
            $this->getInstaller()->getResolvedPackages(PhpExtensionPackage::class),
            fn (PhpExtensionPackage $ext) => $ext->isBuildShared() && !$ext->isBuildWithPhp()
        );

        if (empty($sharedExts)) {
            return '';
        }

        $ret = ' -d "extension_dir=' . BUILD_MODULES_PATH . '"';
        foreach ($sharedExts as $ext) {
            $extConfig = PackageConfig::get($ext->getName(), 'php-extension', []);
            if ($extConfig['zend-extension'] ?? false) {
                $ret .= ' -d "zend_extension=' . $ext->getExtensionName() . '"';
            } else {
                $ret .= ' -d "extension=' . $ext->getExtensionName() . '"';
            }
        }

        return $ret;
    }

    /**
     * Escape PHP test file content for inline `-r` usage.
     * Strips <?php / declare, replaces newlines and special shell characters.
     */
    private static function escapeInlineTest(string $code): string
    {
        return str_replace(
            ['<?php', 'declare(strict_types=1);', "\n", '"', '$', '!'],
            ['', '', '', '\"', '\$', '"\'!\'"'],
            $code
        );
    }
}
