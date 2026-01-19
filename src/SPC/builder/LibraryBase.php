<?php

declare(strict_types=1);

namespace SPC\builder;

use SPC\exception\SPCException;
use SPC\exception\SPCInternalException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\Downloader;
use SPC\store\FileSystem;
use SPC\store\LockFile;
use SPC\store\SourceManager;
use SPC\util\GlobalValueTrait;

abstract class LibraryBase
{
    use GlobalValueTrait;

    /** @var string */
    public const NAME = 'unknown';

    protected string $source_dir;

    protected array $dependencies = [];

    protected bool $patched = false;

    public function __construct(?string $source_dir = null)
    {
        if (static::NAME === 'unknown') {
            throw new SPCInternalException('Please set the NAME constant in ' . static::class);
        }
        $this->source_dir = $source_dir ?? (SOURCE_PATH . DIRECTORY_SEPARATOR . Config::getLib(static::NAME, 'source'));
    }

    /**
     * Try to install or build this library.
     * @param bool $force If true, force install or build
     */
    public function setup(bool $force = false): int
    {
        $source = Config::getLib(static::NAME, 'source');
        // if source is locked as pre-built, we just tryInstall it
        $pre_built_name = Downloader::getPreBuiltLockName($source);
        if (($lock = LockFile::get($pre_built_name)) && $lock['lock_as'] === SPC_DOWNLOAD_PRE_BUILT) {
            return $this->tryInstall($lock, $force);
        }
        return $this->tryBuild($force);
    }

    /**
     * Get library name.
     */
    public function getName(): string
    {
        return static::NAME;
    }

    /**
     * Get current lib source root dir.
     */
    public function getSourceDir(): string
    {
        return $this->source_dir;
    }

    /**
     * Get current lib dependencies.
     *
     * @return array<string, LibraryBase>
     */
    public function getDependencies(bool $recursive = false): array
    {
        // 非递归情况下直接返回通过 addLibraryDependency 方法添加的依赖
        if (!$recursive) {
            return $this->dependencies;
        }

        $deps = [];

        $added = 1;
        while ($added !== 0) {
            $added = 0;
            foreach ($this->dependencies as $depName => $dep) {
                foreach ($dep->getDependencies(true) as $depdepName => $depdep) {
                    if (!in_array($depdepName, array_keys($deps), true)) {
                        $deps[$depdepName] = $depdep;
                        ++$added;
                    }
                }
                if (!in_array($depName, array_keys($deps), true)) {
                    $deps[$depName] = $dep;
                }
            }
        }

        return $deps;
    }

    /**
     * Calculate dependencies for current library.
     */
    public function calcDependency(): void
    {
        // Add dependencies from the configuration file. Here, choose different metadata based on the operating system.
        /*
        Rules:
            If it is a Windows system, try the following dependencies in order: lib-depends-windows, lib-depends-win, lib-depends.
            If it is a macOS system, try the following dependencies in order: lib-depends-macos, lib-depends-unix, lib-depends.
            If it is a Linux system, try the following dependencies in order: lib-depends-linux, lib-depends-unix, lib-depends.
        */
        foreach (Config::getLib(static::NAME, 'lib-depends', []) as $dep_name) {
            $this->addLibraryDependency($dep_name);
        }
        foreach (Config::getLib(static::NAME, 'lib-suggests', []) as $dep_name) {
            $this->addLibraryDependency($dep_name, true);
        }
    }

    /**
     * Get config static libs.
     */
    public function getStaticLibs(): array
    {
        return Config::getLib(static::NAME, 'static-libs', []);
    }

    /**
     * Get config headers.
     */
    public function getHeaders(): array
    {
        return Config::getLib(static::NAME, 'headers', []);
    }

    /**
     * Get binary files.
     */
    public function getBinaryFiles(): array
    {
        return Config::getLib(static::NAME, 'bin', []);
    }

    public function tryInstall(array $lock, bool $force_install = false): int
    {
        $install_file = $lock['filename'];
        if ($force_install) {
            logger()->info('Installing required library [' . static::NAME . '] from pre-built binaries');

            // Extract files
            try {
                FileSystem::extractPackage($install_file, $lock['source_type'], DOWNLOAD_PATH . '/' . $install_file, BUILD_ROOT_PATH);
                $this->install();
                return LIB_STATUS_OK;
            } catch (SPCException $e) {
                logger()->error('Failed to extract pre-built library [' . static::NAME . ']: ' . $e->getMessage());
                return LIB_STATUS_INSTALL_FAILED;
            }
        }
        if (!$this->isLibraryInstalled()) {
            return $this->tryInstall($lock, true);
        }
        return LIB_STATUS_ALREADY;
    }

    /**
     * Try to build this library, before build, we check first.
     *
     * BUILD_STATUS_OK if build success
     * BUILD_STATUS_ALREADY if already built
     * BUILD_STATUS_FAILED if build failed
     */
    public function tryBuild(bool $force_build = false): int
    {
        if (file_exists($this->source_dir . '/.spc.patched')) {
            $this->patched = true;
        }
        // force means just build
        if ($force_build) {
            $type = Config::getLib(static::NAME, 'type', 'lib');
            logger()->info('Building required ' . $type . ' [' . static::NAME . ']');

            // extract first if not exists
            if (!is_dir($this->source_dir)) {
                $this->getBuilder()->emitPatchPoint('before-library[' . static::NAME . ']-extract');
                SourceManager::initSource(libs: [static::NAME], source_only: true);
                $this->getBuilder()->emitPatchPoint('after-library[' . static::NAME . ']-extract');
            }

            if (!$this->patched && $this->patchBeforeBuild()) {
                file_put_contents($this->source_dir . '/.spc.patched', 'PATCHED!!!');
            }
            $this->getBuilder()->emitPatchPoint('before-library[' . static::NAME . ']-build');
            $this->build();
            $this->installLicense();
            $this->getBuilder()->emitPatchPoint('after-library[' . static::NAME . ']-build');
            return LIB_STATUS_OK;
        }

        if (!$this->isLibraryInstalled()) {
            return $this->tryBuild(true);
        }
        // if all the files exist at this point, skip the compilation process
        return LIB_STATUS_ALREADY;
    }

    public function validate(): void
    {
        // do nothing, just throw wrong usage exception if not valid
    }

    /**
     * Get current lib version
     *
     * @return null|string Version string or null
     */
    public function getLibVersion(): ?string
    {
        return null;
    }

    /**
     * Get current builder object.
     */
    abstract public function getBuilder(): BuilderBase;

    public function beforePack(): void
    {
        // do something before pack, default do nothing. overwrite this method to do something (e.g. modify pkg-config file)
    }

    /**
     * Patch code before build
     * If you need to patch some code, overwrite this
     * return true if you patched something, false if not
     */
    public function patchBeforeBuild(): bool
    {
        return false;
    }

    /**
     * Patch code before ./buildconf
     * If you need to patch some code, overwrite this
     * return true if you patched something, false if not
     */
    public function patchBeforeBuildconf(): bool
    {
        return false;
    }

    /**
     * Patch code before ./configure
     * If you need to patch some code, overwrite this
     * return true if you patched something, false if not
     */
    public function patchBeforeConfigure(): bool
    {
        return false;
    }

    /**
     * Patch code before windows configure.bat
     * If you need to patch some code, overwrite this
     * return true if you patched something, false if not
     */
    public function patchBeforeWindowsConfigure(): bool
    {
        return false;
    }

    /**
     * Patch code before make
     * If you need to patch some code, overwrite this
     * return true if you patched something, false if not
     */
    public function patchBeforeMake(): bool
    {
        return false;
    }

    /**
     * Patch php-config after embed was built
     * Example: imap requires -lcrypt
     */
    public function patchPhpConfig(): bool
    {
        return false;
    }

    /**
     * Build this library.
     */
    abstract protected function build();

    protected function install(): void
    {
        // replace placeholders if BUILD_ROOT_PATH/.spc-extract-placeholder.json exists
        $replace_item_file = BUILD_ROOT_PATH . '/.spc-extract-placeholder.json';
        if (!file_exists($replace_item_file)) {
            return;
        }
        $replace_items = json_decode(file_get_contents($replace_item_file), true);
        if (!is_array($replace_items)) {
            throw new SPCInternalException("Invalid placeholder file: {$replace_item_file}");
        }
        $placeholders = get_pack_replace();
        // replace placeholders in BUILD_ROOT_PATH
        foreach ($replace_items as $item) {
            $filepath = BUILD_ROOT_PATH . "/{$item}";
            FileSystem::replaceFileStr(
                $filepath,
                array_values($placeholders),
                array_keys($placeholders),
            );
        }
        // remove placeholder file
        unlink($replace_item_file);
    }

    /**
     * Add lib dependency
     */
    protected function addLibraryDependency(string $name, bool $optional = false): void
    {
        $dep_lib = $this->getBuilder()->getLib($name);
        if ($dep_lib) {
            $this->dependencies[$name] = $dep_lib;
            return;
        }
        if (!$optional) {
            throw new WrongUsageException(static::NAME . " requires library {$name} but it is not included");
        }
        logger()->debug('enabling ' . static::NAME . " without {$name}");
    }

    protected function getSnakeCaseName(): string
    {
        return str_replace('-', '_', static::NAME);
    }

    /**
     * Install license files in buildroot directory
     */
    protected function installLicense(): void
    {
        $source = Config::getLib($this->getName(), 'source');
        FileSystem::createDir(BUILD_ROOT_PATH . "/source-licenses/{$source}");
        $license_files = Config::getSource($source)['license'] ?? [];
        if (is_assoc_array($license_files)) {
            $license_files = [$license_files];
        }
        foreach ($license_files as $index => $license) {
            if ($license['type'] === 'text') {
                FileSystem::writeFile(BUILD_ROOT_PATH . "/source-licenses/{$source}/{$index}.txt", $license['text']);
                continue;
            }
            if ($license['type'] === 'file') {
                copy($this->source_dir . '/' . $license['path'], BUILD_ROOT_PATH . "/source-licenses/{$source}/{$index}.txt");
            }
        }
    }

    protected function isLibraryInstalled(): bool
    {
        foreach (Config::getLib(static::NAME, 'static-libs', []) as $name) {
            if (!file_exists(BUILD_LIB_PATH . "/{$name}")) {
                return false;
            }
        }
        foreach (Config::getLib(static::NAME, 'headers', []) as $name) {
            if (!file_exists(BUILD_INCLUDE_PATH . "/{$name}")) {
                return false;
            }
        }
        $pkg_config_path = getenv('PKG_CONFIG_PATH') ?: '';
        $search_paths = array_filter(explode(is_unix() ? ':' : ';', $pkg_config_path));
        foreach (Config::getLib(static::NAME, 'pkg-configs', []) as $name) {
            $found = false;
            foreach ($search_paths as $path) {
                if (file_exists($path . "/{$name}.pc")) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }
        foreach (Config::getLib(static::NAME, 'bin', []) as $name) {
            if (!file_exists(BUILD_BIN_PATH . "/{$name}")) {
                return false;
            }
        }
        return true;
    }
}
