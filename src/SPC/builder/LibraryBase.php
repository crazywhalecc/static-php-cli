<?php

declare(strict_types=1);

namespace SPC\builder;

use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\Config;

/**
 * Lib 库的基类操作对象
 */
abstract class LibraryBase
{
    /** @var string lib 依赖名称，必须重写 */
    public const NAME = 'unknown';

    /** @var string lib 依赖的根目录 */
    protected string $source_dir;

    /** @var array 依赖列表 */
    protected array $dependencies = [];

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
     * 获取 lib 库的根目录
     */
    public function getSourceDir(): string
    {
        return $this->source_dir;
    }

    /**
     * 获取当前 lib 库的所有依赖列表
     *
     * @param  bool                       $recursive 是否递归获取（默认为 False）
     * @return array<string, LibraryBase> 依赖的 Map
     */
    public function getDependencies(bool $recursive = false): array
    {
        // 非递归情况下直接返回通过 addLibraryDependency 方法添加的依赖
        if (!$recursive) {
            return $this->dependencies;
        }

        // 下面为递归获取依赖列表，根据依赖顺序
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
     * 计算依赖列表，不符合依赖将抛出异常
     *
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function calcDependency(): void
    {
        // 先从配置文件添加依赖，这里根据不同的操作系统分别选择不同的元信息
        /*
        选择规则：
            如果是 Windows 系统，则依次尝试有无 lib-depends-windows、lib-depends-win、lib-depends。
            如果是 macOS 系统，则依次尝试 lib-depends-darwin、lib-depends-unix、lib-depends。
            如果是 Linux 系统，则依次尝试 lib-depends-linux、lib-depends-unix、lib-depends。
         */
        foreach (Config::getLib(static::NAME, 'lib-depends', []) as $dep_name) {
            $this->addLibraryDependency($dep_name);
        }
        foreach (Config::getLib(static::NAME, 'lib-suggests', []) as $dep_name) {
            $this->addLibraryDependency($dep_name, true);
        }
    }

    /**
     * 获取当前库编译出来获取到的静态库文件列表
     *
     * @return string[]            获取编译出来后的需要的静态库文件列表
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function getStaticLibs(): array
    {
        return Config::getLib(static::NAME, 'static-libs', []);
    }

    /**
     * 获取当前 lib 编译出来的 C Header 文件列表
     *
     * @return string[]            获取编译出来后需要的 C Header 文件列表
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function getHeaders(): array
    {
        return Config::getLib(static::NAME, 'headers', []);
    }

    /**
     * 证明该库是否已编译好且就绪，如果没有就绪，内部会调用 build 来进行构建该库
     *
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function tryBuild(bool $force_build = false): int
    {
        // 传入 true，表明直接编译
        if ($force_build) {
            logger()->info('Building required library [' . static::NAME . ']');
            $this->build();
            return BUILD_STATUS_OK;
        }

        // 看看这些库是不是存在，如果不存在，则调用编译并返回结果状态
        foreach ($this->getStaticLibs() as $name) {
            if (!file_exists(BUILD_LIB_PATH . "/{$name}")) {
                $this->tryBuild(true);
                return BUILD_STATUS_OK;
            }
        }
        // 头文件同理
        foreach ($this->getHeaders() as $name) {
            if (!file_exists(BUILD_INCLUDE_PATH . "/{$name}")) {
                $this->tryBuild(true);
                return BUILD_STATUS_OK;
            }
        }
        // pkg-config 做特殊处理，如果是 pkg-config 就检查有没有 pkg-config 二进制
        if ($this instanceof MacOSLibraryBase && static::NAME === 'pkg-config' && !file_exists(BUILD_ROOT_PATH . '/bin/pkg-config')) {
            $this->tryBuild(true);
            return BUILD_STATUS_OK;
        }
        // 到这里说明所有的文件都存在，就跳过编译
        return BUILD_STATUS_ALREADY;
    }

    /**
     * 获取构建当前 lib 的 Builder 对象
     */
    abstract public function getBuilder(): BuilderBase;

    /**
     * 构建该库需要调用的命令和操作
     *
     * @throws RuntimeException
     */
    abstract protected function build();

    /**
     * 添加 lib 库的依赖库
     *
     * @param  string           $name     依赖名称
     * @param  bool             $optional 是否是可选依赖（默认为 False）
     * @throws RuntimeException
     */
    protected function addLibraryDependency(string $name, bool $optional = false): void
    {
        // Log::i("add $name as dep of {$this->name}");
        $dep_lib = $this->getBuilder()->getLib($name);
        if (!$dep_lib) {
            if (!$optional) {
                throw new RuntimeException(static::NAME . " requires library {$name}");
            }
            logger()->debug('enabling ' . static::NAME . " without {$name}");
        } else {
            $this->dependencies[$name] = $dep_lib;
        }
    }
}
