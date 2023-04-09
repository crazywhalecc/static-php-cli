<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\exception\DownloaderException;
use SPC\exception\ExceptionHandler;
use SPC\exception\FileSystemException;
use SPC\exception\InvalidArgumentException;
use SPC\exception\RuntimeException;
use SPC\store\Config;
use SPC\store\Downloader;
use SPC\util\Patcher;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** @noinspection PhpUnused */
class FetchSourceCommand extends BaseCommand
{
    protected static $defaultName = 'fetch';

    protected string $php_major_ver;

    protected InputInterface $input;

    public function configure()
    {
        $this->setDescription('Fetch required sources');
        $this->addArgument('extensions', InputArgument::REQUIRED, 'The extensions will be compiled, comma separated');
        $this->addArgument('libraries', InputArgument::REQUIRED, 'The libraries will be compiled, comma separated');
        $this->addOption('hash', null, null, 'Hash only');
        $this->addOption('shallow-clone', null, null, 'Clone shallow');
        $this->addOption('with-openssl11', null, null, 'Use openssl 1.1');
        $this->addOption('with-php', null, InputOption::VALUE_REQUIRED, 'version in major.minor format like 8.1', '8.1');
        $this->addOption('clean', null, null, 'Clean old download cache and source before fetch');
        $this->addOption('all', 'A', null, 'Fetch all sources that static-php-cli needed');
    }

    public function initialize(InputInterface $input, OutputInterface $output)
    {
        // --all 等于 "" ""，也就是所有东西都要下载
        if ($input->getOption('all')) {
            $input->setArgument('extensions', '');
            $input->setArgument('libraries', '');
        }
        parent::initialize($input, $output);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        try {
            // 匹配版本
            $ver = $this->php_major_ver = $input->getOption('with-php') ?? '8.1';
            define('SPC_BUILD_PHP_VERSION', $ver);
            preg_match('/^\d+\.\d+$/', $ver, $matches);
            if (!$matches) {
                logger()->error("bad version arg: {$ver}, x.y required!");
                return 1;
            }

            // 删除旧资源
            if ($input->getOption('clean')) {
                logger()->warning('You are doing some operations that not recoverable: removing directories below');
                logger()->warning(SOURCE_PATH);
                logger()->warning(DOWNLOAD_PATH);
                logger()->warning('I will remove these dir after you press [Enter] !');
                echo 'Confirm operation? [Yes] ';
                $r = strtolower(trim(fgets(STDIN)));
                if ($r !== 'yes' && $r !== '') {
                    logger()->notice('Operation canceled.');
                    return 1;
                }
                if (PHP_OS_FAMILY === 'Windows') {
                    f_passthru('rmdir /s /q ' . SOURCE_PATH);
                    f_passthru('rmdir /s /q ' . DOWNLOAD_PATH);
                } else {
                    f_passthru('rm -rf ' . SOURCE_PATH);
                    f_passthru('rm -rf ' . DOWNLOAD_PATH);
                }
            }

            // 使用浅克隆可以减少调用 git 命令下载资源时的存储空间占用
            if ($input->getOption('shallow-clone')) {
                define('GIT_SHALLOW_CLONE', true);
            }

            // 读取源配置，随便读一个source，用于缓存 source 配置
            Config::getSource('openssl');

            // 是否启用openssl11
            if ($input->getOption('with-openssl11')) {
                logger()->debug('Using openssl 1.1');
                // 手动修改配置
                Config::$source['openssl']['regex'] = '/href="(?<file>openssl-(?<version>1.[^"]+)\.tar\.gz)\"/';
            }

            // 默认预选 phpmicro
            $chosen_sources = ['micro'];

            // 从参数中获取要编译的 libraries，并转换为数组
            $libraries = array_map('trim', array_filter(explode(',', $input->getArgument('libraries'))));
            if ($libraries) {
                foreach ($libraries as $lib) {
                    // 从 lib 的 config 中找到对应 source 资源名称，组成一个 lib 的 source 列表
                    $src_name = Config::getLib($lib, 'source');
                    $chosen_sources[] = $src_name;
                }
            } else { // 如果传入了空串，那么代表 fetch 所有包
                $chosen_sources = [...$chosen_sources, ...array_map(fn ($x) => $x['source'], array_values(Config::getLibs()))];
            }

            // 从参数中获取要编译的 extensions，并转换为数组
            $extensions = array_map('trim', array_filter(explode(',', $input->getArgument('extensions'))));
            if ($extensions) {
                foreach ($extensions as $lib) {
                    if (Config::getExt($lib, 'type') !== 'builtin') {
                        $src_name = Config::getExt($lib, 'source');
                        $chosen_sources[] = $src_name;
                    }
                }
            } else {
                foreach (Config::getExts() as $ext) {
                    if ($ext['type'] !== 'builtin') {
                        $chosen_sources[] = $ext['source'];
                    }
                }
            }
            $chosen_sources = array_unique($chosen_sources);

            // 是否只hash，不下载资源
            if ($input->getOption('hash')) {
                $hash = $this->doHash($chosen_sources);
                $output->writeln($hash);
                return 0;
            }

            // 创建目录
            f_mkdir(SOURCE_PATH);
            f_mkdir(DOWNLOAD_PATH);

            // 下载 PHP
            array_unshift($chosen_sources, 'php-src');
            // 下载别的依赖资源
            $cnt = count($chosen_sources);
            $ni = 0;
            foreach ($chosen_sources as $name) {
                ++$ni;
                logger()->info("Fetching source {$name} [{$ni}/{$cnt}]");
                Downloader::fetchSource($name, Config::getSource($name));
            }

            // patch 每份资源只需一次，如果已经下载好的资源已经patch了，就标记一下不patch了
            if (!file_exists(SOURCE_PATH . '/.patched')) {
                $this->doPatch();
            } else {
                logger()->notice('sources already patched');
            }

            // 打印拉取资源用时
            $time = round(microtime(true) - START_TIME, 3);
            logger()->info('Fetch complete, used ' . $time . ' s !');
            return 0;
        } catch (\Throwable $e) {
            // 不开 debug 模式就不要再显示复杂的调试栈信息了
            if ($input->getOption('debug')) {
                ExceptionHandler::getInstance()->handle($e);
            } else {
                logger()->emergency($e->getMessage() . ', previous message: ' . $e->getPrevious()?->getMessage());
            }
            return 1;
        }
    }

    /**
     * 计算资源名称列表的 Hash
     *
     * @param  array                    $chosen_sources 要计算 hash 的资源名称列表
     * @throws InvalidArgumentException
     * @throws DownloaderException
     * @throws FileSystemException
     */
    private function doHash(array $chosen_sources): string
    {
        $files = [];
        foreach ($chosen_sources as $name) {
            $source = Config::getSource($name);
            $filename = match ($source['type']) {
                'ghtar' => Downloader::getLatestGithubTarball($name, $source)[1],
                'ghtagtar' => Downloader::getLatestGithubTarball($name, $source, 'tags')[1],
                'ghrel' => Downloader::getLatestGithubRelease($name, $source)[1],
                'filelist' => Downloader::getFromFileList($name, $source)[1],
                'url' => $source['filename'] ?? basename($source['url']),
                'git' => null,
                default => throw new InvalidArgumentException('unknown source type: ' . $source['type']),
            };
            if ($filename !== null) {
                logger()->info("found {$name} source: {$filename}");
                $files[] = $filename;
            }
        }
        return hash('sha256', implode('|', $files));
    }

    /**
     * 在拉回资源后，需要对一些文件做一些补丁 patch
     *
     * @throws FileSystemException
     * @throws RuntimeException
     */
    private function doPatch(): void
    {
        // swow 需要软链接内部的文件夹才能正常编译
        if (!file_exists(SOURCE_PATH . '/php-src/ext/swow')) {
            Patcher::patchSwow();
        }
        // patch 一些 PHP 的资源，以便编译
        Patcher::patchPHPDepFiles();

        // openssl 3 需要 patch 额外的东西
        if (!$this->input->getOption('with-openssl11') && $this->php_major_ver === '8.0') {
            Patcher::patchOpenssl3();
        }

        // openssl1.1.1q 在 MacOS 上直接编译会报错，patch 一下
        // @see: https://github.com/openssl/openssl/issues/18720
        if ($this->input->getOption('with-openssl11') && file_exists(SOURCE_PATH . '/openssl/test/v3ext.c') && PHP_OS_FAMILY === 'Darwin') {
            Patcher::patchDarwinOpenssl11();
        }

        // 标记 patch 完成，避免重复 patch
        file_put_contents(SOURCE_PATH . '/.patched', '');
    }
}
