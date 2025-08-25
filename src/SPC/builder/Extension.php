<?php

declare(strict_types=1);

namespace SPC\builder;

use SPC\exception\EnvironmentException;
use SPC\exception\SPCException;
use SPC\exception\ValidationException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\FileSystem;
use SPC\toolchain\ToolchainManager;
use SPC\toolchain\ZigToolchain;
use SPC\util\SPCConfigUtil;
use SPC\util\SPCTarget;

class Extension
{
    protected array $dependencies = [];

    protected bool $build_shared = false;

    protected bool $build_static = false;

    protected string $source_dir;

    public function __construct(protected string $name, protected BuilderBase $builder)
    {
        $ext_type = Config::getExt($this->name, 'type');
        $unix_only = Config::getExt($this->name, 'unix-only', false);
        $windows_only = Config::getExt($this->name, 'windows-only', false);
        if (PHP_OS_FAMILY !== 'Windows' && $windows_only) {
            throw new EnvironmentException("{$ext_type} extension {$name} is not supported on Linux and macOS platform");
        }
        if (PHP_OS_FAMILY === 'Windows' && $unix_only) {
            throw new EnvironmentException("{$ext_type} extension {$name} is not supported on Windows platform");
        }
        // set source_dir for builtin
        if ($ext_type === 'builtin') {
            $this->source_dir = SOURCE_PATH . '/php-src/ext/' . $this->name;
        } elseif ($ext_type === 'external') {
            $source = Config::getExt($this->name, 'source');
            if ($source === null) {
                throw new ValidationException("{$ext_type} extension {$name} source not found", validation_module: "Extension [{$name}] loader");
            }
            $source_path = Config::getSource($source)['path'] ?? null;
            $source_path = $source_path === null ? SOURCE_PATH . '/' . $source : SOURCE_PATH . '/' . $source_path;
            $this->source_dir = $source_path;
        } else {
            $this->source_dir = SOURCE_PATH . '/php-src';
        }
    }

    public function getFrameworks(): array
    {
        return Config::getExt($this->getName(), 'frameworks', []);
    }

    /**
     * 获取开启该扩展的 PHP 编译添加的参数
     */
    public function getConfigureArg(bool $shared = false): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => $this->getWindowsConfigureArg($shared),
            'Darwin',
            'Linux',
            'BSD' => $this->getUnixConfigureArg($shared),
            default => throw new WrongUsageException(PHP_OS_FAMILY . ' build is not supported yet'),
        };
    }

    /**
     * 根据 ext 的 arg-type 获取对应开启的参数，一般都是 --enable-xxx 和 --with-xxx
     */
    public function getEnableArg(bool $shared = false): string
    {
        $escapedPath = str_replace("'", '', escapeshellarg(BUILD_ROOT_PATH)) !== BUILD_ROOT_PATH || str_contains(BUILD_ROOT_PATH, ' ') ? escapeshellarg(BUILD_ROOT_PATH) : BUILD_ROOT_PATH;
        $_name = str_replace('_', '-', $this->name);
        return match ($arg_type = Config::getExt($this->name, 'arg-type', 'enable')) {
            'enable' => '--enable-' . $_name . ($shared ? '=shared' : '') . ' ',
            'enable-path' => '--enable-' . $_name . '=' . ($shared ? 'shared,' : '') . $escapedPath . ' ',
            'with' => '--with-' . $_name . ($shared ? '=shared' : '') . ' ',
            'with-path' => '--with-' . $_name . '=' . ($shared ? 'shared,' : '') . $escapedPath . ' ',
            'none', 'custom' => '',
            default => throw new WrongUsageException("argType does not accept {$arg_type}, use [enable/with/with-path] ."),
        };
    }

    /**
     * 导出当前扩展依赖的所有 lib 库生成的 .a 静态编译库文件，以字符串形式导出，用空格分割
     */
    public function getLibFilesString(): string
    {
        $ret = array_map(
            fn ($x) => $x->getStaticLibFiles(),
            $this->getLibraryDependencies(recursive: true)
        );
        return implode(' ', $ret);
    }

    /**
     * 检查下依赖就行了，作用是导入依赖给 Extension 对象，今后可以对库依赖进行选择性处理
     */
    public function checkDependency(): static
    {
        foreach (Config::getExt($this->name, 'lib-depends', []) as $name) {
            $this->addLibraryDependency($name);
        }
        foreach (Config::getExt($this->name, 'lib-suggests', []) as $name) {
            $this->addLibraryDependency($name, true);
        }
        foreach (Config::getExt($this->name, 'ext-depends', []) as $name) {
            $this->addExtensionDependency($name);
        }
        foreach (Config::getExt($this->name, 'ext-suggests', []) as $name) {
            $this->addExtensionDependency($name, true);
        }
        return $this;
    }

    public function getExtensionDependency(): array
    {
        return array_filter($this->dependencies, fn ($x) => $x instanceof Extension);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * returns extension dist name
     */
    public function getDistName(): string
    {
        return $this->name;
    }

    public function getWindowsConfigureArg(bool $shared = false): string
    {
        return $this->getEnableArg();
        // Windows is not supported yet
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        return $this->getEnableArg($shared);
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
     * Patch code before ./configure.bat for Windows
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
        if (SPCTarget::getTargetOS() === 'Linux' && $this->isBuildShared() && ($objs = getenv('SPC_EXTRA_RUNTIME_OBJECTS'))) {
            FileSystem::replaceFileRegex(
                SOURCE_PATH . '/php-src/Makefile',
                "/^(shared_objects_{$this->getName()}\\s*=.*)$/m",
                "$1 {$objs}",
            );
            return true;
        }
        return false;
    }

    /**
     * Patch code before shared extension phpize
     * If you need to patch some code, overwrite this
     * return true if you patched something, false if not
     */
    public function patchBeforeSharedPhpize(): bool
    {
        return false;
    }

    /**
     * Patch code before shared extension ./configure
     * If you need to patch some code, overwrite this
     * return true if you patched something, false if not
     */
    public function patchBeforeSharedConfigure(): bool
    {
        return false;
    }

    /**
     * Patch code before shared extension make
     * If you need to patch some code, overwrite this
     * return true if you patched something, false if not
     */
    public function patchBeforeSharedMake(): bool
    {
        $config = (new SPCConfigUtil($this->builder))->config([$this->getName()], array_map(fn ($l) => $l->getName(), $this->builder->getLibs()));
        [$staticLibs] = $this->splitLibsIntoStaticAndShared($config['libs']);
        FileSystem::replaceFileRegex(
            $this->source_dir . '/Makefile',
            '/^(.*_SHARED_LIBADD\s*=.*)$/m',
            '$1 ' . trim($staticLibs)
        );
        if ($objs = getenv('SPC_EXTRA_RUNTIME_OBJECTS')) {
            FileSystem::replaceFileRegex(
                $this->source_dir . '/Makefile',
                "/^(shared_objects_{$this->getName()}\\s*=.*)$/m",
                "$1 {$objs}",
            );
        }
        return true;
    }

    /**
     * @return string
     *                returns a command line string with all required shared extensions to load
     *                i.e.; pdo_pgsql would return:
     *
     * `-d "extension=pgsql" -d "extension=pdo_pgsql"`
     */
    public function getSharedExtensionLoadString(): string
    {
        $loaded = [];
        $order = [];

        $resolve = function ($extension) use (&$resolve, &$loaded, &$order) {
            if (!$extension instanceof Extension) {
                return;
            }
            if (isset($loaded[$extension->getName()])) {
                return;
            }
            $loaded[$extension->getName()] = true;

            foreach ($extension->dependencies as $dependency) {
                $resolve($dependency);
            }

            $order[] = $extension;
        };

        $resolve($this);

        $ret = '';
        foreach ($order as $ext) {
            if ($ext instanceof self && $ext->isBuildShared()) {
                if (Config::getExt($ext->getName(), 'type', false) === 'addon') {
                    continue;
                }
                if (Config::getExt($ext->getName(), 'zend-extension', false) === true) {
                    $ret .= " -d \"zend_extension={$ext->getName()}\"";
                } else {
                    $ret .= " -d \"extension={$ext->getName()}\"";
                }
            }
        }

        if ($ret !== '') {
            $ret = ' -d "extension_dir=' . BUILD_MODULES_PATH . '"' . $ret;
        }

        return $ret;
    }

    public function runCliCheckUnix(): void
    {
        // Run compile check if build target is cli
        // If you need to run some check, overwrite this or add your assert in src/globals/ext-tests/{extension_name}.php
        $sharedExtensions = $this->getSharedExtensionLoadString();
        [$ret, $out] = shell()->execWithResult(BUILD_BIN_PATH . '/php -n' . $sharedExtensions . ' --ri "' . $this->getDistName() . '"');
        if ($ret !== 0) {
            throw new ValidationException(
                "extension {$this->getName()} failed compile check: php-cli returned {$ret}",
                validation_module: 'Extension ' . $this->getName() . ' sanity check'
            );
        }

        if (file_exists(ROOT_DIR . '/src/globals/ext-tests/' . $this->getName() . '.php')) {
            // Trim additional content & escape special characters to allow inline usage
            $test = str_replace(
                ['<?php', 'declare(strict_types=1);', "\n", '"', '$', '!'],
                ['', '', '', '\"', '\$', '"\'!\'"'],
                file_get_contents(ROOT_DIR . '/src/globals/ext-tests/' . $this->getName() . '.php')
            );

            [$ret, $out] = shell()->execWithResult(BUILD_BIN_PATH . '/php -n' . $sharedExtensions . ' -r "' . trim($test) . '"');
            if ($ret !== 0) {
                throw new ValidationException(
                    "extension {$this->getName()} failed sanity check. Code: {$ret}, output: " . implode("\n", $out),
                    validation_module: 'Extension ' . $this->getName() . ' function check'
                );
            }
        }
    }

    public function runCliCheckWindows(): void
    {
        // Run compile check if build target is cli
        // If you need to run some check, overwrite this or add your assert in src/globals/ext-tests/{extension_name}.php
        [$ret] = cmd()->execWithResult(BUILD_ROOT_PATH . '/bin/php.exe -n --ri "' . $this->getDistName() . '"', false);
        if ($ret !== 0) {
            throw new ValidationException("extension {$this->getName()} failed compile check: php-cli returned {$ret}", validation_module: "Extension {$this->getName()} sanity check");
        }

        if (file_exists(FileSystem::convertPath(ROOT_DIR . '/src/globals/ext-tests/' . $this->getName() . '.php'))) {
            // Trim additional content & escape special characters to allow inline usage
            $test = str_replace(
                ['<?php', 'declare(strict_types=1);', "\n", '"', '$'],
                ['', '', '', '\"', '$'],
                file_get_contents(FileSystem::convertPath(ROOT_DIR . '/src/globals/ext-tests/' . $this->getName() . '.php'))
            );

            [$ret] = cmd()->execWithResult(BUILD_ROOT_PATH . '/bin/php.exe -n -r "' . trim($test) . '"');
            if ($ret !== 0) {
                throw new ValidationException(
                    "extension {$this->getName()} failed function check",
                    validation_module: "Extension {$this->getName()} function check"
                );
            }
        }
    }

    public function validate(): void
    {
        // do nothing, just throw wrong usage exception if not valid
    }

    /**
     * Build shared extension
     */
    public function buildShared(array $visited = []): void
    {
        try {
            if (Config::getExt($this->getName(), 'type') === 'builtin' || Config::getExt($this->getName(), 'build-with-php') === true) {
                if (file_exists(BUILD_MODULES_PATH . '/' . $this->getName() . '.so')) {
                    logger()->info('Shared extension [' . $this->getName() . '] was already built by php-src/configure (' . $this->getName() . '.so)');
                    return;
                }
                if (Config::getExt($this->getName(), 'build-with-php') === true) {
                    logger()->warning('Shared extension [' . $this->getName() . '] did not build with php-src/configure (' . $this->getName() . '.so)');
                    logger()->warning('Try deleting your build and source folders and running `spc build`` again.');
                    return;
                }
            }
            if (file_exists(BUILD_MODULES_PATH . '/' . $this->getName() . '.so')) {
                logger()->info('Shared extension [' . $this->getName() . '] was already built, skipping (' . $this->getName() . '.so)');
                return;
            }
            logger()->info('Building extension [' . $this->getName() . '] as shared extension (' . $this->getName() . '.so)');
            foreach ($this->dependencies as $dependency) {
                if (!$dependency instanceof Extension) {
                    continue;
                }
                if (!$dependency->isBuildStatic() && !in_array($dependency->getName(), $visited)) {
                    logger()->info('extension ' . $this->getName() . ' requires extension ' . $dependency->getName());
                    $dependency->buildShared([...$visited, $this->getName()]);
                }
            }
            if (Config::getExt($this->getName(), 'type') === 'addon') {
                return;
            }
            match (PHP_OS_FAMILY) {
                'Darwin', 'Linux' => $this->buildUnixShared(),
                default => throw new WrongUsageException(PHP_OS_FAMILY . ' build shared extensions is not supported yet'),
            };
        } catch (SPCException $e) {
            $e->bindExtensionInfo(['extension_name' => $this->getName()]);
            throw $e;
        }
    }

    /**
     * Build shared extension for Unix
     */
    public function buildUnixShared(): void
    {
        $config = (new SPCConfigUtil($this->builder))->config(
            [$this->getName()],
            array_map(fn ($l) => $l->getName(), $this->getLibraryDependencies(recursive: true)),
            $this->builder->getOption('with-suggested-exts'),
            $this->builder->getOption('with-suggested-libs'),
        );
        [$staticLibs, $sharedLibs] = $this->splitLibsIntoStaticAndShared($config['libs']);
        $preStatic = PHP_OS_FAMILY === 'Darwin' ? '' : '-Wl,--start-group ';
        $postStatic = PHP_OS_FAMILY === 'Darwin' ? '' : ' -Wl,--end-group ';
        $env = [
            'CFLAGS' => $config['cflags'],
            'CXXFLAGS' => $config['cflags'],
            'LDFLAGS' => $config['ldflags'],
            'LIBS' => clean_spaces("{$preStatic} {$staticLibs} {$postStatic} {$sharedLibs}"),
            'LD_LIBRARY_PATH' => BUILD_LIB_PATH,
        ];
        if (ToolchainManager::getToolchainClass() === ZigToolchain::class && SPCTarget::getTargetOS() === 'Linux') {
            $env['SPC_COMPILER_EXTRA'] = '-lstdc++';
        }

        if ($this->patchBeforeSharedPhpize()) {
            logger()->info("Extension [{$this->getName()}] patched before shared phpize");
        }

        // prepare configure args
        shell()->cd($this->source_dir)
            ->setEnv($env)
            ->appendEnv($this->getExtraEnv())
            ->exec(BUILD_BIN_PATH . '/phpize');

        if ($this->patchBeforeSharedConfigure()) {
            logger()->info("Extension [{$this->getName()}] patched before shared configure");
        }

        shell()->cd($this->source_dir)
            ->setEnv($env)
            ->appendEnv($this->getExtraEnv())
            ->exec(
                './configure ' . $this->getUnixConfigureArg(true) .
                ' --with-php-config=' . BUILD_BIN_PATH . '/php-config ' .
                '--enable-shared --disable-static'
            );

        if ($this->patchBeforeSharedMake()) {
            logger()->info("Extension [{$this->getName()}] patched before shared make");
        }

        shell()->cd($this->source_dir)
            ->setEnv($env)
            ->appendEnv($this->getExtraEnv())
            ->exec('make clean')
            ->exec('make -j' . $this->builder->concurrency)
            ->exec('make install');
    }

    /**
     * Get current extension version
     *
     * @return null|string Version string or null
     */
    public function getExtVersion(): ?string
    {
        return null;
    }

    public function setBuildStatic(): void
    {
        if (!in_array('static', Config::getExtTarget($this->name))) {
            throw new WrongUsageException("Extension [{$this->name}] does not support static build!");
        }
        $this->build_static = true;
    }

    public function setBuildShared(): void
    {
        if (!in_array('shared', Config::getExtTarget($this->name))) {
            throw new WrongUsageException("Extension [{$this->name}] does not support shared build!");
        }
        $this->build_shared = true;
    }

    public function isBuildShared(): bool
    {
        return $this->build_shared;
    }

    public function isBuildStatic(): bool
    {
        return $this->build_static;
    }

    protected function addLibraryDependency(string $name, bool $optional = false): void
    {
        $depLib = $this->builder->getLib($name);
        if (!$depLib) {
            if (!$optional) {
                throw new WrongUsageException("extension {$this->name} requires library {$name}");
            }
            logger()->info("enabling {$this->name} without library {$name}");
        } else {
            $this->dependencies[] = $depLib;
        }
    }

    protected function addExtensionDependency(string $name, bool $optional = false): void
    {
        $depExt = $this->builder->getExt($name);
        if (!$depExt) {
            if (!$optional) {
                throw new WrongUsageException("{$this->name} requires extension {$name} which is not included");
            }
            logger()->info("enabling {$this->name} without extension {$name}");
        } else {
            $this->dependencies[] = $depExt;
        }
    }

    protected function getExtraEnv(): array
    {
        return [];
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

    private function getLibraryDependencies(bool $recursive = false): array
    {
        $ret = array_filter($this->dependencies, fn ($x) => $x instanceof LibraryBase);
        if (!$recursive) {
            return $ret;
        }

        $deps = [];

        $added = 1;
        while ($added !== 0) {
            $added = 0;
            foreach ($ret as $depName => $dep) {
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

        if (array_key_exists(0, $deps)) {
            $zero = [0 => $deps[0]];
            unset($deps[0]);
            return $zero + $deps;
        }
        return $deps;
    }
}
