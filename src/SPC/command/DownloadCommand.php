<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\builder\traits\UnixSystemUtilTrait;
use SPC\exception\DownloaderException;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\Config;
use SPC\store\Downloader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('download', 'Download required sources', ['fetch'])]
class DownloadCommand extends BaseCommand
{
    use UnixSystemUtilTrait;

    protected string $php_major_ver;

    public function configure()
    {
        $this->addArgument('sources', InputArgument::REQUIRED, 'The sources will be compiled, comma separated');
        $this->addOption('shallow-clone', null, null, 'Clone shallow');
        $this->addOption('with-openssl11', null, null, 'Use openssl 1.1');
        $this->addOption('with-php', null, InputOption::VALUE_REQUIRED, 'version in major.minor format like 8.1', '8.1');
        $this->addOption('clean', null, null, 'Clean old download cache and source before fetch');
        $this->addOption('all', 'A', null, 'Fetch all sources that static-php-cli needed');
        $this->addOption('from-zip', 'Z', InputOption::VALUE_REQUIRED, 'Fetch from zip archive');
    }

    public function initialize(InputInterface $input, OutputInterface $output)
    {
        // --all 等于 "" ""，也就是所有东西都要下载
        if ($input->getOption('all') || $input->getOption('clean') || $input->getOption('from-zip')) {
            $input->setArgument('sources', '');
        }
        parent::initialize($input, $output);
    }

    /**
     * @throws DownloaderException
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function handle(): int
    {
        // 删除旧资源
        if ($this->getOption('clean')) {
            logger()->warning('You are doing some operations that not recoverable: removing directories below');
            logger()->warning(SOURCE_PATH);
            logger()->warning(DOWNLOAD_PATH);
            logger()->warning(BUILD_ROOT_PATH);
            logger()->alert('I will remove these dir after 5 seconds !');
            sleep(5);
            if (PHP_OS_FAMILY === 'Windows') {
                f_passthru('rmdir /s /q ' . SOURCE_PATH);
                f_passthru('rmdir /s /q ' . DOWNLOAD_PATH);
                f_passthru('rmdir /s /q ' . BUILD_ROOT_PATH);
            } else {
                f_passthru('rm -rf ' . SOURCE_PATH . '/*');
                f_passthru('rm -rf ' . DOWNLOAD_PATH . '/*');
                f_passthru('rm -rf ' . BUILD_ROOT_PATH . '/*');
            }
            return 0;
        }

        // --from-zip
        if ($path = $this->getOption('from-zip')) {
            if (!file_exists($path)) {
                logger()->critical('File ' . $path . ' not exist or not a zip archive.');
                return 1;
            }
            // remove old download files first
            if (is_dir(DOWNLOAD_PATH)) {
                logger()->warning('You are doing some operations that not recoverable: removing directories below');
                logger()->warning(DOWNLOAD_PATH);
                logger()->alert('I will remove these dir after 5 seconds !');
                sleep(5);
                f_passthru((PHP_OS_FAMILY === 'Windows' ? 'rmdir /s /q ' : 'rm -rf ') . DOWNLOAD_PATH);
            }
            // unzip command check
            if (PHP_OS_FAMILY !== 'Windows' && !$this->findCommand('unzip')) {
                logger()->critical('Missing unzip command, you need to install it first !');
                logger()->critical('You can use "bin/spc doctor" command to check and install required tools');
                return 1;
            }
            // create downloads
            try {
                if (PHP_OS_FAMILY !== 'Windows') {
                    f_passthru('mkdir ' . DOWNLOAD_PATH . ' && cd ' . DOWNLOAD_PATH . ' && unzip ' . escapeshellarg($path));
                }
                // Windows TODO

                if (!file_exists(DOWNLOAD_PATH . '/.lock.json')) {
                    throw new RuntimeException('.lock.json not exist in "downloads/"');
                }
            } catch (RuntimeException $e) {
                logger()->critical('Extract failed: ' . $e->getMessage());
                return 1;
            }
            logger()->info('Extract success');
            return 0;
        }

        // Define PHP major version
        $ver = $this->php_major_ver = $this->getOption('with-php') ?? '8.1';
        define('SPC_BUILD_PHP_VERSION', $ver);
        preg_match('/^\d+\.\d+$/', $ver, $matches);
        if (!$matches) {
            logger()->error("bad version arg: {$ver}, x.y required!");
            return 1;
        }

        // Use shallow-clone can reduce git resource download
        if ($this->getOption('shallow-clone')) {
            define('GIT_SHALLOW_CLONE', true);
        }

        // To read config
        Config::getSource('openssl');

        // use openssl 1.1
        if ($this->getOption('with-openssl11')) {
            logger()->debug('Using openssl 1.1');
            // 手动修改配置
            Config::$source['openssl']['regex'] = '/href="(?<file>openssl-(?<version>1.[^"]+)\.tar\.gz)\"/';
        }

        // get source list that will be downloaded
        $sources = array_map('trim', array_filter(explode(',', $this->getArgument('sources'))));
        if (empty($sources)) {
            $sources = array_keys(Config::getSources());
        }
        $chosen_sources = $sources;

        // Download them
        f_mkdir(DOWNLOAD_PATH);
        $cnt = count($chosen_sources);
        $ni = 0;
        foreach ($chosen_sources as $source) {
            ++$ni;
            logger()->info("Fetching source {$source} [{$ni}/{$cnt}]");
            Downloader::downloadSource($source, Config::getSource($source));
        }
        // 打印拉取资源用时
        $time = round(microtime(true) - START_TIME, 3);
        logger()->info('Download complete, used ' . $time . ' s !');
        return 0;
    }
}
