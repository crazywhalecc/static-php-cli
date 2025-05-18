<?php

declare(strict_types=1);

namespace SPC\builder;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\FileSystem;
use SPC\util\SPCConfigUtil;

class Extension
{
    protected array $dependencies = [];

    protected bool $build_shared = false;

    protected bool $build_static = false;

    protected string $source_dir;

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function __construct(protected string $name, protected BuilderBase $builder)
    {
        $ext_type = Config::getExt($this->name, 'type');
        $unix_only = Config::getExt($this->name, 'unix-only', false);
        $windows_only = Config::getExt($this->name, 'windows-only', false);
        if (PHP_OS_FAMILY !== 'Windows' && $windows_only) {
            throw new RuntimeException("{$ext_type} extension {$name} is not supported on Linux and macOS platform");
        }
        if (PHP_OS_FAMILY === 'Windows' && $unix_only) {
            throw new RuntimeException("{$ext_type} extension {$name} is not supported on Windows platform");
        }
        // set source_dir for builtin
        if ($ext_type === 'builtin') {
            $this->source_dir = SOURCE_PATH . '/php-src/ext/' . $this->name;
        } elseif ($ext_type === 'external') {
            $source = Config::getExt($this->name, 'source');
            if ($source === null) {
                throw new RuntimeException("{$ext_type} extension {$name} source not found");
            }
            $source_path = Config::getSource($source)['path'] ?? null;
            $source_path = $source_path === null ? SOURCE_PATH . '/' . $source : SOURCE_PATH . '/' . $source_path;
            $this->source_dir = $source_path;
        } else {
            $this->source_dir = SOURCE_PATH . '/php-src';
        }
    }

    /**
     * 获取开启该扩展的 PHP 编译添加的参数
     *
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    public function getConfigureArg(): string
    {
        $arg = $this->getEnableArg();
        switch (PHP_OS_FAMILY) {
            case 'Windows':
                $arg .= $this->getWindowsConfigureArg();
                break;
            case 'Darwin':
            case 'Linux':
            case 'BSD':
                $arg .= $this->getUnixConfigureArg();
                break;
        }
        return $arg;
    }

    /**
     * 根据 ext 的 arg-type 获取对应开启的参数，一般都是 --enable-xxx 和 --with-xxx
     *
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    public function getEnableArg(): string
    {
        $_name = str_replace('_', '-', $this->name);
        return match ($arg_type = Config::getExt($this->name, 'arg-type', 'enable')) {
            'enable' => '--enable-' . $_name . ' ',
            'with' => '--with-' . $_name . ' ',
            'with-prefix' => '--with-' . $_name . '="' . BUILD_ROOT_PATH . '" ',
            'none', 'custom' => '',
            default => throw new WrongUsageException("argType does not accept {$arg_type}, use [enable/with/with-prefix] ."),
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
     *
     * @throws RuntimeException
     * @throws FileSystemException
     * @throws WrongUsageException
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

    public function getWindowsConfigureArg(): string
    {
        return '';
        // Windows is not supported yet
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        return '';
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
     * Patch code before shared extension ./configure
     * If you need to patch some code, overwrite this
     * return true if you patched something, false if not
     */
    public function patchBeforeSharedBuild(): bool
    {
        return false;
    }

    /**
     * Run shared extension check when cli is enabled
     * @throws RuntimeException
     */
    public function runSharedExtensionCheckUnix(): void
    {
        [$ret] = shell()->execWithResult(BUILD_BIN_PATH . '/php -n -d "extension=' . BUILD_MODULES_PATH . '/' . $this->getName() . '.so" --ri ' . $this->getName());
        if ($ret !== 0) {
            throw new RuntimeException($this->getName() . '.so failed to load');
        }
        if ($this->isBuildStatic()) {
            logger()->warning($this->getName() . '.so test succeeded, but has little significance since it is also compiled in statically.');
        }
    }

    /**
     * @throws RuntimeException
     */
    public function runCliCheckUnix(): void
    {
        // Run compile check if build target is cli
        // If you need to run some check, overwrite this or add your assert in src/globals/ext-tests/{extension_name}.php
        // If check failed, throw RuntimeException
        [$ret] = shell()->execWithResult(BUILD_ROOT_PATH . '/bin/php -n --ri "' . $this->getDistName() . '"', false);
        if ($ret !== 0) {
            throw new RuntimeException('extension ' . $this->getName() . ' failed compile check: php-cli returned ' . $ret);
        }

        if (file_exists(ROOT_DIR . '/src/globals/ext-tests/' . $this->getName() . '.php')) {
            // Trim additional content & escape special characters to allow inline usage
            $test = str_replace(
                ['<?php', 'declare(strict_types=1);', "\n", '"', '$', '!'],
                ['', '', '', '\"', '\$', '"\'!\'"'],
                file_get_contents(ROOT_DIR . '/src/globals/ext-tests/' . $this->getName() . '.php')
            );

            [$ret, $out] = shell()->execWithResult(BUILD_ROOT_PATH . '/bin/php -n -r "' . trim($test) . '"');
            if ($ret !== 0) {
                if ($this->builder->getOption('debug')) {
                    var_dump($out);
                }
                throw new RuntimeException('extension ' . $this->getName() . ' failed sanity check');
            }
        }
    }

    /**
     * @throws RuntimeException
     */
    public function runCliCheckWindows(): void
    {
        // Run compile check if build target is cli
        // If you need to run some check, overwrite this or add your assert in src/globals/ext-tests/{extension_name}.php
        // If check failed, throw RuntimeException
        [$ret] = cmd()->execWithResult(BUILD_ROOT_PATH . '/bin/php.exe -n --ri "' . $this->getDistName() . '"', false);
        if ($ret !== 0) {
            throw new RuntimeException('extension ' . $this->getName() . ' failed compile check: php-cli returned ' . $ret);
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
                throw new RuntimeException('extension ' . $this->getName() . ' failed sanity check');
            }
        }
    }

    public function validate(): void
    {
        // do nothing, just throw wrong usage exception if not valid
    }

    /**
     * Build shared extension
     *
     * @throws WrongUsageException
     * @throws RuntimeException
     */
    public function buildShared(): void
    {
        if (file_exists(BUILD_MODULES_PATH . '/' . $this->getName() . '.so')) {
            logger()->info('extension ' . $this->getName() . ' already built, skipping');
            return;
        }
        match (PHP_OS_FAMILY) {
            'Darwin', 'Linux' => $this->buildUnixShared(),
            default => throw new WrongUsageException(PHP_OS_FAMILY . ' build shared extensions is not supported yet'),
        };
    }

    /**
     * Build shared extension for Unix
     *
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function buildUnixShared(): void
    {
        $config = (new SPCConfigUtil($this->builder))->config([$this->getName()]);
        $env = [
            'CFLAGS' => $config['cflags'],
            'LDFLAGS' => $config['ldflags'],
            'LIBS' => $config['libs'],
            'LD_LIBRARY_PATH' => BUILD_LIB_PATH,
        ];
        // prepare configure args
        shell()->cd($this->source_dir)
            ->setEnv($env)
            ->execWithEnv(BUILD_BIN_PATH . '/phpize')
            ->execWithEnv('./configure ' . $this->getUnixConfigureArg(true) . ' --with-php-config=' . BUILD_BIN_PATH . '/php-config --enable-shared --disable-static')
            ->execWithEnv('make clean')
            ->execWithEnv('make -j' . $this->builder->concurrency);

        // copy shared library
        FileSystem::createDir(BUILD_MODULES_PATH);
        $extensionDirFile = (getenv('EXTENSION_DIR') ?: $this->source_dir . '/modules') . '/' . $this->getName() . '.so';
        $sourceDirFile = $this->source_dir . '/modules/' . $this->getName() . '.so';
        if (file_exists($extensionDirFile)) {
            copy($extensionDirFile, BUILD_MODULES_PATH . '/' . $this->getName() . '.so');
        } elseif (file_exists($sourceDirFile)) {
            copy($sourceDirFile, BUILD_MODULES_PATH . '/' . $this->getName() . '.so');
        } else {
            throw new RuntimeException('extension ' . $this->getName() . ' built successfully, but into an unexpected location.');
        }
        // check shared extension with php-cli
        if (file_exists(BUILD_BIN_PATH . '/php')) {
            $this->runSharedExtensionCheckUnix();
        }
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

    /**
     * @throws RuntimeException
     */
    protected function addLibraryDependency(string $name, bool $optional = false): void
    {
        $depLib = $this->builder->getLib($name);
        if (!$depLib) {
            if (!$optional) {
                throw new RuntimeException("extension {$this->name} requires library {$name}");
            }
            logger()->info("enabling {$this->name} without library {$name}");
        } else {
            $this->dependencies[] = $depLib;
        }
    }

    /**
     * @throws RuntimeException
     */
    protected function addExtensionDependency(string $name, bool $optional = false): void
    {
        $depExt = $this->builder->getExt($name);
        if (!$depExt) {
            if (!$optional) {
                throw new RuntimeException("{$this->name} requires extension {$name}");
            }
            logger()->info("enabling {$this->name} without extension {$name}");
        } else {
            $this->dependencies[] = $depExt;
        }
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

        return $deps;
    }
}
