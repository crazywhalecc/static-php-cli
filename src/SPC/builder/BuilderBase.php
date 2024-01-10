<?php

declare(strict_types=1);

namespace SPC\builder;

use SPC\exception\ExceptionHandler;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\SourceExtractor;
use SPC\util\CustomExt;

abstract class BuilderBase
{
    /** @var int Concurrency */
    public int $concurrency = 1;

    /** @var array<string, LibraryBase> libraries */
    protected array $libs = [];

    /** @var array<string, Extension> extensions */
    protected array $exts = [];

    /** @var bool compile libs only (just mark it) */
    protected bool $libs_only = false;

    /** @var array<string, mixed> compile options */
    protected array $options = [];

    /** @var string patch point name */
    protected string $patch_point = '';

    /**
     * Build libraries
     *
     * @param  array<string>       $sorted_libraries Libraries to build (if not empty, must sort first)
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     * @internal
     */
    abstract public function buildLibs(array $sorted_libraries);

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
    public function getExts(): array
    {
        return $this->exts;
    }

    /**
     * Check if there is a cpp extensions or libraries.
     *
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    public function hasCpp(): bool
    {
        // judge cpp-extension
        $exts = array_keys($this->getExts());
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
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws \ReflectionException
     * @throws WrongUsageException
     * @internal
     */
    public function proveExts(array $extensions): void
    {
        CustomExt::loadCustomExt();
        $this->emitPatchPoint('before-php-extract');
        SourceExtractor::initSource(sources: ['php-src']);
        $this->emitPatchPoint('after-php-extract');
        if ($this->getPHPVersionID() >= 80000) {
            $this->emitPatchPoint('before-micro-extract');
            SourceExtractor::initSource(sources: ['micro']);
            $this->emitPatchPoint('after-micro-extract');
        }
        $this->emitPatchPoint('before-exts-extract');
        SourceExtractor::initSource(exts: $extensions);
        $this->emitPatchPoint('after-exts-extract');
        foreach ($extensions as $extension) {
            $class = CustomExt::getExtClass($extension);
            $ext = new $class($extension, $this);
            $this->addExt($ext);
        }

        foreach ($this->exts as $ext) {
            $ext->checkDependency();
        }
    }

    /**
     * Start to build PHP
     *
     * @param int $build_target Build target, see BUILD_TARGET_*
     */
    abstract public function buildPHP(int $build_target = BUILD_TARGET_NONE);

    /**
     * Generate extension enable arguments for configure.
     * e.g. --enable-mbstring
     *
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    public function makeExtensionArgs(): string
    {
        $ret = [];
        foreach ($this->exts as $ext) {
            logger()->info($ext->getName() . ' is using ' . $ext->getConfigureArg());
            $ret[] = trim($ext->getConfigureArg());
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
     *
     * @throws RuntimeException
     * @throws WrongUsageException
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

        throw new RuntimeException('PHP version file format is malformed, please remove it and download again');
    }

    public function getPHPVersion(): string
    {
        if (!file_exists(SOURCE_PATH . '/php-src/main/php_version.h')) {
            throw new WrongUsageException('PHP source files are not available, you need to download them first');
        }
        $file = file_get_contents(SOURCE_PATH . '/php-src/main/php_version.h');
        if (preg_match('/PHP_VERSION "(.*)"/', $file, $match) !== 0) {
            return $match[1];
        }

        throw new RuntimeException('PHP version file format is malformed, please remove it and download again');
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

    public function emitPatchPoint(string $point_name): void
    {
        $this->patch_point = $point_name;
        if (($patches = $this->getOption('with-added-patch', [])) === []) {
            return;
        }

        foreach ($patches as $patch) {
            try {
                if (!file_exists($patch)) {
                    throw new RuntimeException("Additional patch script file {$patch} not found!");
                }
                logger()->debug('Running additional patch script: ' . $patch);
                require $patch;
            } catch (\Throwable $e) {
                logger()->critical('Patch script ' . $patch . ' failed to run.');
                if ($this->getOption('debug')) {
                    ExceptionHandler::getInstance()->handle($e);
                } else {
                    logger()->critical('Please check with --debug option to see more details.');
                }
            }
        }
    }

    /**
     * Check if all libs are downloaded.
     * If not, throw exception.
     *
     * @throws RuntimeException
     */
    protected function checkLibsSource(): void
    {
        $not_downloaded = [];
        foreach ($this->libs as $lib) {
            if (!file_exists($lib->getSourceDir())) {
                $not_downloaded[] = $lib::NAME;
            }
        }
        if ($not_downloaded !== []) {
            throw new RuntimeException(
                '"' . implode(', ', $not_downloaded) .
                '" totally ' . count($not_downloaded) .
                ' source' . (count($not_downloaded) === 1 ? '' : 's') .
                ' not downloaded, maybe you need to "fetch" ' . (count($not_downloaded) === 1 ? 'it' : 'them') . ' first?'
            );
        }
    }

    /**
     * Generate micro extension test php code.
     */
    protected function generateMicroExtTests(): string
    {
        $php = "<?php\n\necho '[micro-test-start]' . PHP_EOL;\n";

        foreach ($this->getExts() as $ext) {
            $ext_name = $ext->getDistName();
            $php .= "echo 'Running micro with {$ext_name} test' . PHP_EOL;\n";
            $php .= "assert(extension_loaded('{$ext_name}'));\n\n";
        }
        $php .= "echo '[micro-test-end]';\n";
        return $php;
    }
}
