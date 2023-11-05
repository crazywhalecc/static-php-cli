<?php

declare(strict_types=1);

namespace SPC\builder;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;

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
            If it is a macOS system, try the following dependencies in order: lib-depends-darwin, lib-depends-unix, lib-depends.
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
            logger()->info('Building required library [' . static::NAME . ']');
            if (!$this->patched && $this->patchBeforeBuild()) {
                file_put_contents($this->source_dir . '/.spc.patched', 'PATCHED!!!');
            }
            $this->build();
            return BUILD_STATUS_OK;
        }

        // check if these libraries exist, if not, invoke compilation and return the result status
        foreach ($this->getStaticLibs() as $name) {
            if (!file_exists(BUILD_LIB_PATH . "/{$name}")) {
                $this->tryBuild(true);
                return BUILD_STATUS_OK;
            }
        }
        // header files the same
        foreach ($this->getHeaders() as $name) {
            if (!file_exists(BUILD_INCLUDE_PATH . "/{$name}")) {
                $this->tryBuild(true);
                return BUILD_STATUS_OK;
            }
        }
        // pkg-config is treated specially. If it is pkg-config, check if the pkg-config binary exists
        if (static::NAME === 'pkg-config' && !file_exists(BUILD_ROOT_PATH . '/bin/pkg-config')) {
            $this->tryBuild(true);
            return BUILD_STATUS_OK;
        }
        // if all the files exist at this point, skip the compilation process
        return BUILD_STATUS_ALREADY;
    }

    /**
     * Patch before build, overwrite this and return true to patch libs.
     */
    public function patchBeforeBuild(): bool
    {
        return false;
    }

    /**
     * Get current builder object.
     */
    abstract public function getBuilder(): BuilderBase;

    /**
     * Build this library.
     *
     * @throws RuntimeException
     */
    abstract protected function build();

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
}
