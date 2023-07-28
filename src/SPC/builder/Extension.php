<?php

declare(strict_types=1);

namespace SPC\builder;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;

class Extension
{
    protected array $dependencies = [];

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
    }

    /**
     * 获取开启该扩展的 PHP 编译添加的参数
     *
     * @throws FileSystemException|RuntimeException
     * @throws WrongUsageException
     */
    public function getConfigureArg(): string
    {
        $arg = $this->getEnableArg();
        switch (PHP_OS_FAMILY) {
            case 'Windows':
                $arg = $this->getWindowsConfigureArg();
                break;
            case 'Darwin':
            case 'Linux':
                $arg .= $this->getUnixConfigureArg();
                break;
        }
        return $arg;
    }

    /**
     * 根据 ext 的 arg-type 获取对应开启的参数，一般都是 --enable-xxx 和 --with-xxx
     *
     * @throws FileSystemException
     * @throws RuntimeException
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

    public function getUnixConfigureArg(): string
    {
        return '';
    }

    /**
     * Patch code before ./buildconf
     * If you need to patch some code, overwrite this and return true
     */
    public function patchBeforeBuildconf(): bool
    {
        return false;
    }

    /**
     * Patch code before ./configure
     * If you need to patch some code, overwrite this and return true
     */
    public function patchBeforeConfigure(): bool
    {
        return false;
    }

    /**
     * Patch code before make
     * If you need to patch some code, overwrite this and return true
     */
    public function patchBeforeMake(): bool
    {
        return false;
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
