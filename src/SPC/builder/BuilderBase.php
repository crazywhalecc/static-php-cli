<?php

declare(strict_types=1);

namespace SPC\builder;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\FileSystem;
use SPC\util\CustomExt;
use SPC\util\DependencyUtil;

abstract class BuilderBase
{
    /** @var bool 是否启用 ZTS 线程安全 */
    public bool $zts = false;

    /** @var string 编译目标架构 */
    public string $arch;

    /** @var string GNU 格式的编译目标架构 */
    public string $gnu_arch;

    /** @var int 编译进程数 */
    public int $concurrency = 1;

    /** @var array<string, LibraryBase> 要编译的 libs 列表 */
    protected array $libs = [];

    /** @var array<string, Extension> 要编译的扩展列表 */
    protected array $exts = [];

    /** @var array<int, string> 要编译的扩展列表（仅名字列表，用于最后生成编译的扩展列表给 micro） */
    protected array $plain_extensions = [];

    /** @var bool 本次编译是否只编译 libs，不编译 PHP */
    protected bool $libs_only = false;

    /** @var bool 是否 strip 最终的二进制 */
    protected bool $strip = true;

    /**
     * 构建指定列表的 libs
     *
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function buildLibs(array $libraries): void
    {
        // 通过扫描目录查找 lib
        $support_lib_list = [];
        $classes = FileSystem::getClassesPsr4(
            ROOT_DIR . '/src/SPC/builder/' . osfamily2dir() . '/library',
            'SPC\\builder\\' . osfamily2dir() . '\\library'
        );
        foreach ($classes as $class) {
            if (defined($class . '::NAME') && $class::NAME !== 'unknown' && Config::getLib($class::NAME) !== null) {
                $support_lib_list[$class::NAME] = $class;
            }
        }

        // 如果传入了空，则默认检查和安置所有支持的lib，libraries为要build的，support_lib_list为支持的列表
        if ($libraries === [] && $this->isLibsOnly()) {
            $libraries = array_keys($support_lib_list);
        }

        // 排序 libs，根据依赖计算一个新的列表出来
        $libraries = DependencyUtil::getLibsByDeps($libraries);
        // 过滤不支持的库后添加
        foreach ($libraries as $library) {
            if (!isset($support_lib_list[$library])) {
                throw new RuntimeException(
                    'library [' . $library . '] is in the lib.json list but not supported to compile, but in the future I will support it!'
                );
            }
            $lib = new ($support_lib_list[$library])($this);
            $this->addLib($lib);
        }

        // 计算依赖，经过这里的遍历，如果没有抛出异常，说明依赖符合要求，可以继续下面的
        foreach ($this->libs as $lib) {
            $lib->calcDependency();
        }

        $this->initSource(libs: $libraries);

        // 构建库
        foreach ($this->libs as $lib) {
            match ($lib->tryBuild()) {
                BUILD_STATUS_OK => logger()->info('lib [' . $lib::NAME . '] build success'),
                BUILD_STATUS_ALREADY => logger()->notice('lib [' . $lib::NAME . '] already built'),
                BUILD_STATUS_FAILED => logger()->error('lib [' . $lib::NAME . '] build failed'),
                default => logger()->warning('lib [' . $lib::NAME . '] build status unknown'),
            };
        }
    }

    /**
     * 添加要编译的 Lib 库
     *
     * @param LibraryBase $library Lib 库对象
     */
    public function addLib(LibraryBase $library): void
    {
        $this->libs[$library::NAME] = $library;
    }

    /**
     * 获取要编译的 Lib 库对象
     *
     * @param string $name 库名称
     */
    public function getLib(string $name): ?LibraryBase
    {
        return $this->libs[$name] ?? null;
    }

    /**
     * 添加要编译的扩展
     *
     * @param Extension $extension 扩展对象
     */
    public function addExt(Extension $extension): void
    {
        $this->exts[$extension->getName()] = $extension;
    }

    /**
     * 获取要编译的扩展对象
     *
     * @param string $name 扩展名称
     */
    public function getExt(string $name): ?Extension
    {
        return $this->exts[$name] ?? null;
    }

    /**
     * 设置本次 Builder 是否为仅编译库的模式
     */
    public function setLibsOnly(bool $status = true): void
    {
        $this->libs_only = $status;
    }

    /**
     * 检验 ext 扩展列表是否合理，并声明 Extension 对象，检查扩展的依赖
     *
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function proveExts(array $extensions): void
    {
        CustomExt::loadCustomExt();
        $this->initSource(sources: ['php-src']);
        if ($this->getPHPVersionID() >= 80000) {
            $this->initSource(sources: ['micro']);
        }
        $this->initSource(exts: $extensions);
        foreach ($extensions as $extension) {
            $class = CustomExt::getExtClass($extension);
            $ext = new $class($extension, $this);
            $this->addExt($ext);
        }

        foreach ($this->exts as $ext) {
            // 检查下依赖就行了，作用是导入依赖给 Extension 对象，今后可以对库依赖进行选择性处理
            $ext->checkDependency();
        }

        $this->plain_extensions = $extensions;
    }

    /**
     * 开始构建 PHP
     *
     * @param int  $build_target 规则
     * @param bool $bloat        保留
     */
    abstract public function buildPHP(int $build_target = BUILD_TARGET_NONE, bool $bloat = false);

    /**
     * 生成依赖的扩展编译启用参数
     * 例如 --enable-mbstring 等
     *
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function makeExtensionArgs(): string
    {
        $ret = [];
        foreach ($this->exts as $ext) {
            $ret[] = trim($ext->getConfigureArg());
        }
        logger()->info('Using configure: ' . implode(' ', $ret));
        return implode(' ', $ret);
    }

    /**
     * 返回是否只编译 libs 的模式
     */
    public function isLibsOnly(): bool
    {
        return $this->libs_only;
    }

    /**
     * 获取当前即将编译的 PHP 的版本 ID，五位数那个
     */
    public function getPHPVersionID(): int
    {
        $file = file_get_contents(SOURCE_PATH . '/php-src/main/php_version.h');
        preg_match('/PHP_VERSION_ID (\d+)/', $file, $match);
        return intval($match[1]);
    }

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
        return implode(', ', $ls);
    }

    public function setStrip(bool $strip): void
    {
        $this->strip = $strip;
    }

    /**
     * 检查是否存在 lib 库对应的源码，如果不存在，则抛出异常
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
                ' not downloaded, maybe you need to "fetch" ' . (count(
                    $not_downloaded
                ) === 1 ? 'it' : 'them') . ' first?'
            );
        }
    }

    protected function initSource(?array $sources = null, ?array $libs = null, ?array $exts = null): void
    {
        if (!file_exists(DOWNLOAD_PATH . '/.lock.json')) {
            throw new WrongUsageException(
                'Download lock file "downloads/.lock.json" not found, maybe you need to download sources first ?'
            );
        }
        $lock = json_decode(FileSystem::readFile(DOWNLOAD_PATH . '/.lock.json'), true);

        $sources_extracted = [];
        // source check exist
        if (is_array($sources)) {
            foreach ($sources as $source) {
                $sources_extracted[$source] = true;
            }
        }
        // lib check source exist
        if (is_array($libs)) {
            foreach ($libs as $lib) {
                // get source name for lib
                $source = Config::getLib($lib, 'source');
                $sources_extracted[$source] = true;
            }
        }
        // ext check source exist
        if (is_array($exts)) {
            foreach ($exts as $ext) {
                // get source name for ext
                if (Config::getExt($ext, 'type') !== 'external') {
                    continue;
                }
                $source = Config::getExt($ext, 'source');
                $sources_extracted[$source] = true;
            }
        }

        // start check
        foreach ($sources_extracted as $source => $item) {
            if (!isset($lock[$source])) {
                throw new WrongUsageException(
                    'Source [' . $source . '] not downloaded, you should download it first !'
                );
            }

            // check source dir exist
            $check = $lock[$source]['move_path'] === null ? SOURCE_PATH . '/' . $source : SOURCE_PATH . '/' . $lock[$source]['move_path'];
            if (!is_dir($check)) {
                FileSystem::extractSource(
                    $source,
                    DOWNLOAD_PATH . '/' . ($lock[$source]['filename'] ?? $lock[$source]['dirname']),
                    $lock[$source]['move_path']
                );
            }
        }
    }
}
