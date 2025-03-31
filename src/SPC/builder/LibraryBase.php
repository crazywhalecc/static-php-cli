<?php

declare(strict_types=1);

namespace SPC\builder;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\Downloader;
use SPC\store\FileSystem;
use SPC\store\SourceManager;

abstract class LibraryBase
{
    /** @var string */
    public const NAME = 'unknown';

    protected string $source_dir;

    protected array $dependencies = [];

    protected bool $patched = false;

    /**
     * @throws RuntimeException
     */
    public function __construct(?string $source_dir = null)
    {
        if (static::NAME === 'unknown') {
            throw new RuntimeException('no unknown!!!!!');
        }
        $this->source_dir = $source_dir ?? (SOURCE_PATH . '/' . static::NAME);
    }

    /**
     * Try to install or build this library.
     * @param  bool                $force If true, force install or build
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function setup(bool $force = false): int
    {
        $lock = json_decode(FileSystem::readFile(DOWNLOAD_PATH . '/.lock.json'), true) ?? [];
        $source = Config::getLib(static::NAME, 'source');
        // if source is locked as pre-built, we just tryInstall it
        $pre_built_name = Downloader::getPreBuiltLockName($source);
        if (isset($lock[$pre_built_name]) && ($lock[$pre_built_name]['lock_as'] ?? SPC_DOWNLOAD_SOURCE) === SPC_DOWNLOAD_PRE_BUILT) {
            return $this->tryInstall($lock[$pre_built_name]['filename'], $force);
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
     *
     * @throws RuntimeException
     * @throws FileSystemException
     * @throws WrongUsageException
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
     *
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    public function getStaticLibs(): array
    {
        return Config::getLib(static::NAME, 'static-libs', []);
    }

    /**
     * Get config headers.
     *
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    public function getHeaders(): array
    {
        return Config::getLib(static::NAME, 'headers', []);
    }

    /**
     * Get binary files.
     *
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    public function getBinaryFiles(): array
    {
        return Config::getLib(static::NAME, 'bin', []);
    }

    /**
     * @throws WrongUsageException
     * @throws FileSystemException
     */
    public function tryInstall(string $install_file, bool $force_install = false): int
    {
        if ($force_install) {
            logger()->info('Installing required library [' . static::NAME . '] from pre-built binaries');

            // Extract files
            try {
                FileSystem::extractPackage($install_file, DOWNLOAD_PATH . '/' . $install_file, BUILD_ROOT_PATH);
                $this->install();
                return LIB_STATUS_OK;
            } catch (FileSystemException|RuntimeException $e) {
                logger()->error('Failed to extract pre-built library [' . static::NAME . ']: ' . $e->getMessage());
                return LIB_STATUS_INSTALL_FAILED;
            }
        }
        foreach ($this->getStaticLibs() as $name) {
            if (!file_exists(BUILD_LIB_PATH . "/{$name}")) {
                $this->tryInstall($install_file, true);
                return LIB_STATUS_OK;
            }
        }
        foreach ($this->getHeaders() as $name) {
            if (!file_exists(BUILD_INCLUDE_PATH . "/{$name}")) {
                $this->tryInstall($install_file, true);
                return LIB_STATUS_OK;
            }
        }
        // pkg-config is treated specially. If it is pkg-config, check if the pkg-config binary exists
        if (static::NAME === 'pkg-config' && !file_exists(BUILD_ROOT_PATH . '/bin/pkg-config')) {
            $this->tryInstall($install_file, true);
            return LIB_STATUS_OK;
        }
        return LIB_STATUS_ALREADY;
    }

    /**
     * Try to build this library, before build, we check first.
     *
     * BUILD_STATUS_OK if build success
     * BUILD_STATUS_ALREADY if already built
     * BUILD_STATUS_FAILED if build failed
     *
     * @throws RuntimeException
     * @throws FileSystemException
     * @throws WrongUsageException
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
                $this->getBuilder()->emitPatchPoint('before-library[ ' . static::NAME . ']-extract');
                SourceManager::initSource(libs: [static::NAME], source_only: true);
                $this->getBuilder()->emitPatchPoint('after-library[ ' . static::NAME . ']-extract');
            }

            if (!$this->patched && $this->patchBeforeBuild()) {
                file_put_contents($this->source_dir . '/.spc.patched', 'PATCHED!!!');
            }
            $this->getBuilder()->emitPatchPoint('before-library[ ' . static::NAME . ']-build');
            $this->build();
            $this->installLicense();
            $this->getBuilder()->emitPatchPoint('after-library[ ' . static::NAME . ']-build');
            return LIB_STATUS_OK;
        }

        // check if these libraries exist, if not, invoke compilation and return the result status
        foreach ($this->getStaticLibs() as $name) {
            if (!file_exists(BUILD_LIB_PATH . "/{$name}")) {
                $this->tryBuild(true);
                return LIB_STATUS_OK;
            }
        }
        // header files the same
        foreach ($this->getHeaders() as $name) {
            if (!file_exists(BUILD_INCLUDE_PATH . "/{$name}")) {
                $this->tryBuild(true);
                return LIB_STATUS_OK;
            }
        }
        // current library is package and binary file is not exists
        if (Config::getLib(static::NAME, 'type', 'lib') === 'package') {
            foreach ($this->getBinaryFiles() as $name) {
                if (!file_exists(BUILD_BIN_PATH . "/{$name}")) {
                    $this->tryBuild(true);
                    return LIB_STATUS_OK;
                }
            }
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
     * Patch code before make
     * If you need to patch some code, overwrite this
     * return true if you patched something, false if not
     */
    public function patchBeforeMake(): bool
    {
        return false;
    }

    /**
     * Build this library.
     *
     * @throws RuntimeException
     */
    abstract protected function build();

    protected function install(): void
    {
        // do something after extracting pre-built files, default do nothing. overwrite this method to do something
    }

    /**
     * Add lib dependency
     *
     * @throws RuntimeException
     */
    protected function addLibraryDependency(string $name, bool $optional = false): void
    {
        $dep_lib = $this->getBuilder()->getLib($name);
        if ($dep_lib) {
            $this->dependencies[$name] = $dep_lib;
            return;
        }
        if (!$optional) {
            throw new RuntimeException(static::NAME . " requires library {$name}");
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
        FileSystem::createDir(BUILD_ROOT_PATH . '/source-licenses/' . $this->getName());
        $source = Config::getLib($this->getName(), 'source');
        $license_files = Config::getSource($source)['license'] ?? [];
        if (is_assoc_array($license_files)) {
            $license_files = [$license_files];
        }
        foreach ($license_files as $index => $license) {
            if ($license['type'] === 'text') {
                FileSystem::writeFile(BUILD_ROOT_PATH . '/source-licenses/' . $this->getName() . "/{$index}.txt", $license['text']);
                continue;
            }
            if ($license['type'] === 'file') {
                copy($this->source_dir . '/' . $license['path'], BUILD_ROOT_PATH . '/source-licenses/' . $this->getName() . "/{$index}.txt");
            }
        }
    }
}
