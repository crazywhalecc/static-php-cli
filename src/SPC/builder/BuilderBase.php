<?php

declare(strict_types=1);

namespace SPC\builder;

use SPC\exception\BuildFailureException;
use SPC\exception\InterruptException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\FileSystem;
use SPC\store\LockFile;
use SPC\store\SourceManager;
use SPC\store\SourcePatcher;
use SPC\util\AttributeMapper;

abstract class BuilderBase
{
    /** @var int Concurrency */
    public int $concurrency = 1;

    /** @var array<string, LibraryBase> libraries */
    protected array $libs = [];

    /** @var array<string, Extension> extensions */
    protected array $exts = [];

    /** @var array<int, string> extension names */
    protected array $ext_list = [];

    /** @var array<int, string> library names */
    protected array $lib_list = [];

    /** @var bool compile libs only (just mark it) */
    protected bool $libs_only = false;

    /** @var array<string, mixed> compile options */
    protected array $options = [];

    /** @var string patch point name */
    protected string $patch_point = '';

    /**
     * Convert libraries to class
     *
     * @param array<string> $sorted_libraries Libraries to build (if not empty, must sort first)
     *
     * @internal
     */
    abstract public function proveLibs(array $sorted_libraries);

    /**
     * Set-Up libraries
     */
    public function setupLibs(): void
    {
        // build all libs
        foreach ($this->libs as $lib) {
            $starttime = microtime(true);
            $status = $lib->setup($this->getOption('rebuild', false));
            match ($status) {
                LIB_STATUS_OK => logger()->info('lib [' . $lib::NAME . '] setup success, took ' . round(microtime(true) - $starttime, 2) . ' s'),
                LIB_STATUS_ALREADY => logger()->notice('lib [' . $lib::NAME . '] already built'),
                LIB_STATUS_INSTALL_FAILED => logger()->error('lib [' . $lib::NAME . '] install failed'),
                default => logger()->warning('lib [' . $lib::NAME . '] build status unknown'),
            };
            if (in_array($status, [LIB_STATUS_BUILD_FAILED, LIB_STATUS_INSTALL_FAILED])) {
                throw new BuildFailureException('Library [' . $lib::NAME . '] setup failed.');
            }
        }
    }

    /**
     * Add library to build.
     *
     * @param LibraryBase $library Library object
     */
    public function addLib(LibraryBase $library): void
    {
        $this->libs[$library::NAME] = $library;
    }

    /**
     * Get library object by name.
     */
    public function getLib(string $name): ?LibraryBase
    {
        return $this->libs[$name] ?? null;
    }

    /**
     * Get all library objects.
     *
     * @return LibraryBase[]
     */
    public function getLibs(): array
    {
        return $this->libs;
    }

    /**
     * Add extension to build.
     */
    public function addExt(Extension $extension): void
    {
        $this->exts[$extension->getName()] = $extension;
    }

    /**
     * Get extension object by name.
     */
    public function getExt(string $name): ?Extension
    {
        return $this->exts[$name] ?? null;
    }

    /**
     * Get all extension objects.
     *
     * @return Extension[]
     */
    public function getExts(bool $including_shared = true): array
    {
        if ($including_shared) {
            return $this->exts;
        }
        return array_filter($this->exts, fn ($ext) => $ext->isBuildStatic());
    }

    /**
     * Check if there is a cpp extensions or libraries.
     */
    public function hasCpp(): bool
    {
        // judge cpp-extension
        $exts = array_keys($this->getExts(false));
        foreach ($exts as $ext) {
            if (Config::getExt($ext, 'cpp-extension', false) === true) {
                return true;
            }
        }
        $libs = array_keys($this->getLibs());
        foreach ($libs as $lib) {
            if (Config::getLib($lib, 'cpp-library', false) === true) {
                return true;
            }
        }
        return false;
    }

    /**
     * Set libs only mode.
     *
     * @internal
     */
    public function setLibsOnly(bool $status = true): void
    {
        $this->libs_only = $status;
    }

    /**
     * Verify the list of "ext" extensions for validity and declare an Extension object to check the dependencies of the extensions.
     *
     * @internal
     */
    public function proveExts(array $static_extensions, array $shared_extensions = [], bool $skip_check_deps = false, bool $skip_extract = false): void
    {
        // judge ext
        foreach ($static_extensions as $ext) {
            // if extension does not support static build, throw exception
            if (!in_array('static', Config::getExtTarget($ext))) {
                throw new WrongUsageException('Extension [' . $ext . '] does not support static build!');
            }
        }
        foreach ($shared_extensions as $ext) {
            // if extension does not support shared build, throw exception
            if (!in_array('shared', Config::getExtTarget($ext)) && !in_array($ext, $shared_extensions)) {
                throw new WrongUsageException('Extension [' . $ext . '] does not support shared build!');
            }
        }
        if (!$skip_extract) {
            $this->emitPatchPoint('before-php-extract');
            SourceManager::initSource(sources: ['php-src'], source_only: true);
            $this->emitPatchPoint('after-php-extract');
            if ($this->getPHPVersionID() >= 80000) {
                $this->emitPatchPoint('before-micro-extract');
                SourceManager::initSource(sources: ['micro'], source_only: true);
                $this->emitPatchPoint('after-micro-extract');
            }
            $this->emitPatchPoint('before-exts-extract');
            SourceManager::initSource(exts: [...$static_extensions, ...$shared_extensions]);
            $this->emitPatchPoint('after-exts-extract');
            // patch micro
            SourcePatcher::patchMicro();
        }

        foreach ([...$static_extensions, ...$shared_extensions] as $extension) {
            $class = AttributeMapper::getExtensionClassByName($extension) ?? Extension::class;
            /** @var Extension $ext */
            $ext = new $class($extension, $this);
            if (in_array($extension, $static_extensions)) {
                $ext->setBuildStatic();
            }
            if (in_array($extension, $shared_extensions)) {
                $ext->setBuildShared();
            }
            $this->addExt($ext);
        }

        if ($skip_check_deps) {
            return;
        }

        foreach ($this->getExts() as $ext) {
            $ext->checkDependency();
        }
        $this->ext_list = [...$static_extensions, ...$shared_extensions];
    }

    /**
     * Start to build PHP
     *
     * @param int $build_target Build target, see BUILD_TARGET_*
     */
    abstract public function buildPHP(int $build_target = BUILD_TARGET_NONE);

    /**
     * Test PHP
     */
    abstract public function testPHP(int $build_target = BUILD_TARGET_NONE);

    /**
     * Build shared extensions.
     */
    public function buildSharedExts(): void
    {
        $lines = file(BUILD_BIN_PATH . '/php-config');
        $extension_dir_line = null;
        foreach ($lines as $key => $value) {
            if (str_starts_with($value, 'extension_dir=')) {
                $lines[$key] = 'extension_dir="' . BUILD_MODULES_PATH . '"' . PHP_EOL;
                $extension_dir_line = $value;
                break;
            }
        }
        file_put_contents(BUILD_BIN_PATH . '/php-config', implode('', $lines));
        FileSystem::replaceFileStr(BUILD_LIB_PATH . '/php/build/phpize.m4', 'test "[$]$1" = "no" && $1=yes', '# test "[$]$1" = "no" && $1=yes');
        FileSystem::createDir(BUILD_MODULES_PATH);
        try {
            foreach ($this->getExts() as $ext) {
                if (!$ext->isBuildShared()) {
                    continue;
                }
                $ext->buildShared();
            }
        } finally {
            FileSystem::replaceFileLineContainsString(BUILD_BIN_PATH . '/php-config', 'extension_dir=', $extension_dir_line);
        }
        FileSystem::replaceFileLineContainsString(BUILD_BIN_PATH . '/php-config', 'extension_dir=', $extension_dir_line);
        FileSystem::replaceFileStr(BUILD_LIB_PATH . '/php/build/phpize.m4', '# test "[$]$1" = "no" && $1=yes', 'test "[$]$1" = "no" && $1=yes');
    }

    /**
     * Generate extension enable arguments for configure.
     * e.g. --enable-mbstring
     */
    public function makeStaticExtensionArgs(): string
    {
        $ret = [];
        foreach ($this->getExts() as $ext) {
            $arg = null;
            if ($ext->isBuildShared() && !$ext->isBuildStatic()) {
                if (
                    (Config::getExt($ext->getName(), 'type') === 'builtin' &&
                        !file_exists(SOURCE_PATH . '/php-src/ext/' . $ext->getName() . '/config.m4')) ||
                    Config::getExt($ext->getName(), 'build-with-php') === true
                ) {
                    $arg = $ext->getConfigureArg(true);
                } else {
                    continue;
                }
            }
            $arg ??= $ext->getConfigureArg();
            logger()->info($ext->getName() . ' is using ' . $arg);
            $ret[] = trim($arg);
        }
        logger()->debug('Using configure: ' . implode(' ', $ret));
        return implode(' ', $ret);
    }

    /**
     * Get libs only mode.
     */
    public function isLibsOnly(): bool
    {
        return $this->libs_only;
    }

    /**
     * Get PHP Version ID from php-src/main/php_version.h
     */
    public function getPHPVersionID(): int
    {
        if (!file_exists(SOURCE_PATH . '/php-src/main/php_version.h')) {
            throw new WrongUsageException('PHP source files are not available, you need to download them first');
        }

        $file = file_get_contents(SOURCE_PATH . '/php-src/main/php_version.h');
        if (preg_match('/PHP_VERSION_ID (\d+)/', $file, $match) !== 0) {
            return intval($match[1]);
        }

        throw new WrongUsageException('PHP version file format is malformed, please remove "./source/php-src" dir and download/extract again');
    }

    public function getPHPVersion(bool $exception_on_failure = true): string
    {
        if (!file_exists(SOURCE_PATH . '/php-src/main/php_version.h')) {
            if (!$exception_on_failure) {
                return 'unknown';
            }
            throw new WrongUsageException('PHP source files are not available, you need to download them first');
        }
        $file = file_get_contents(SOURCE_PATH . '/php-src/main/php_version.h');
        if (preg_match('/PHP_VERSION "(.*)"/', $file, $match) !== 0) {
            return $match[1];
        }
        if (!$exception_on_failure) {
            return 'unknown';
        }
        throw new WrongUsageException('PHP version file format is malformed, please remove it and download again');
    }

    /**
     * Get PHP version from archive file name.
     *
     * @param null|string $file php-*.*.*.tar.gz filename, read from lockfile if empty
     */
    public function getPHPVersionFromArchive(?string $file = null): false|string
    {
        if ($file === null) {
            $lock = LockFile::get('php-src');
            if ($lock === null) {
                return false;
            }
            $file = LockFile::getLockFullPath($lock);
        }
        if (preg_match('/php-(\d+\.\d+\.\d+(?:RC\d+|alpha\d+|beta\d+)?)\.tar\.(?:gz|bz2|xz)/', $file, $match)) {
            return $match[1];
        }
        return false;
    }

    public function getMicroVersion(): false|string
    {
        $file = FileSystem::convertPath(SOURCE_PATH . '/php-src/sapi/micro/php_micro.h');
        if (!file_exists($file)) {
            return false;
        }

        $content = file_get_contents($file);
        $ver = '';
        preg_match('/#define PHP_MICRO_VER_MAJ (\d)/m', $content, $match);
        $ver .= $match[1] . '.';
        preg_match('/#define PHP_MICRO_VER_MIN (\d)/m', $content, $match);
        $ver .= $match[1] . '.';
        preg_match('/#define PHP_MICRO_VER_PAT (\d)/m', $content, $match);
        $ver .= $match[1];
        return $ver;
    }

    /**
     * Get build type name string to display.
     *
     * @param int $type Build target type
     */
    public function getBuildTypeName(int $type): string
    {
        $ls = [];
        if (($type & BUILD_TARGET_CLI) === BUILD_TARGET_CLI) {
            $ls[] = 'cli';
        }
        if (($type & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO) {
            $ls[] = 'micro';
        }
        if (($type & BUILD_TARGET_FPM) === BUILD_TARGET_FPM) {
            $ls[] = 'fpm';
        }
        if (($type & BUILD_TARGET_EMBED) === BUILD_TARGET_EMBED) {
            $ls[] = 'embed';
        }
        if (($type & BUILD_TARGET_FRANKENPHP) === BUILD_TARGET_FRANKENPHP) {
            $ls[] = 'frankenphp';
        }
        return implode(', ', $ls);
    }

    /**
     * Get builder options (maybe changed by user)
     *
     * @param string $key     Option key
     * @param mixed  $default If not exists, return this value
     */
    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Get all builder options
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Set builder options if not exists.
     */
    public function setOptionIfNotExist(string $key, mixed $value): void
    {
        if (!isset($this->options[$key])) {
            $this->options[$key] = $value;
        }
    }

    /**
     * Set builder options.
     */
    public function setOption(string $key, mixed $value): void
    {
        $this->options[$key] = $value;
    }

    public function getEnvString(array $vars = ['cc', 'cxx', 'ar', 'ld']): string
    {
        $env = [];
        foreach ($vars as $var) {
            $var = strtoupper($var);
            if (getenv($var) !== false) {
                $env[] = "{$var}=" . getenv($var);
            }
        }
        return implode(' ', $env);
    }

    /**
     * Get builder patch point name.
     */
    public function getPatchPoint(): string
    {
        return $this->patch_point;
    }

    /**
     * Validate libs and exts can be compiled successfully in current environment
     */
    public function validateLibsAndExts(): void
    {
        foreach ($this->libs as $lib) {
            $lib->validate();
        }
        foreach ($this->getExts() as $ext) {
            $ext->validate();
        }
    }

    public function emitPatchPoint(string $point_name): void
    {
        $this->patch_point = $point_name;
        if (($patches = $this->getOption('with-added-patch', [])) === []) {
            return;
        }

        foreach ($patches as $patch) {
            try {
                if (!file_exists($patch)) {
                    throw new WrongUsageException("Additional patch script file {$patch} not found!");
                }
                logger()->debug('Running additional patch script: ' . $patch);
                require $patch;
            } catch (InterruptException $e) {
                if ($e->getCode() === 0) {
                    logger()->notice('Patch script ' . $patch . ' interrupted' . ($e->getMessage() ? (': ' . $e->getMessage()) : '.'));
                } else {
                    logger()->error('Patch script ' . $patch . ' interrupted with error code [' . $e->getCode() . ']' . ($e->getMessage() ? (': ' . $e->getMessage()) : '.'));
                }
                exit($e->getCode());
            } catch (\Throwable $e) {
                logger()->critical('Patch script ' . $patch . ' failed to run.');
                throw $e;
            }
        }
    }

    public function checkBeforeBuildPHP(int $rule): void
    {
        if (($rule & BUILD_TARGET_FRANKENPHP) === BUILD_TARGET_FRANKENPHP) {
            if (!$this->getOption('enable-zts')) {
                throw new WrongUsageException('FrankenPHP SAPI requires ZTS enabled PHP, build with `--enable-zts`!');
            }
            // frankenphp doesn't support windows, BSD is currently not supported by static-php-cli
            if (!in_array(PHP_OS_FAMILY, ['Linux', 'Darwin'])) {
                throw new WrongUsageException('FrankenPHP SAPI is only available on Linux and macOS!');
            }
            // frankenphp needs package go-xcaddy installed
            $pkg_dir = PKG_ROOT_PATH . '/go-xcaddy-' . arch2gnu(php_uname('m')) . '-' . osfamily2shortname();
            if (!file_exists("{$pkg_dir}/bin/go") || !file_exists("{$pkg_dir}/bin/xcaddy")) {
                global $argv;
                throw new WrongUsageException("FrankenPHP SAPI requires the go-xcaddy package, please install it first: {$argv[0]} install-pkg go-xcaddy");
            }
            // frankenphp needs libxml2 lib on macos, see: https://github.com/php/frankenphp/blob/main/frankenphp.go#L17
            if (PHP_OS_FAMILY === 'Darwin' && !$this->getLib('libxml2')) {
                throw new WrongUsageException('FrankenPHP SAPI for macOS requires libxml2 library, please include the `xml` extension in your build.');
            }
        }
    }

    /**
     * Generate micro extension test php code.
     */
    protected function generateMicroExtTests(): string
    {
        $php = "<?php\n\necho '[micro-test-start]' . PHP_EOL;\n";

        foreach ($this->getExts(false) as $ext) {
            $ext_name = $ext->getDistName();
            if (!empty($ext_name)) {
                $php .= "echo 'Running micro with {$ext_name} test' . PHP_EOL;\n";
                $php .= "assert(extension_loaded('{$ext_name}'));\n\n";
            }
        }
        $php .= "echo '[micro-test-end]';\n";
        return $php;
    }

    protected function getMicroTestTasks(): array
    {
        return [
            'micro_ext_test' => [
                'content' => ($this->getOption('without-micro-ext-test') ? '<?php echo "[micro-test-start][micro-test-end]";' : $this->generateMicroExtTests()),
                'conditions' => [
                    // program success
                    function ($ret) { return $ret === 0; },
                    // program returns expected output
                    function ($ret, $out) {
                        $raw_out = trim(implode('', $out));
                        return str_starts_with($raw_out, '[micro-test-start]') && str_ends_with($raw_out, '[micro-test-end]');
                    },
                ],
            ],
            'micro_zend_bug_test' => [
                'content' => ($this->getOption('without-micro-ext-test') ? '<?php echo "hello";' : file_get_contents(ROOT_DIR . '/src/globals/common-tests/micro_zend_mm_heap_corrupted.txt')),
                'conditions' => [
                    // program success
                    function ($ret) { return $ret === 0; },
                ],
            ],
        ];
    }
}
