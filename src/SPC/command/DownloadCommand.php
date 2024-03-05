<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\builder\traits\UnixSystemUtilTrait;
use SPC\exception\DownloaderException;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\Downloader;
use SPC\util\DependencyUtil;
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

    public function configure(): void
    {
        $this->addArgument('sources', InputArgument::REQUIRED, 'The sources will be compiled, comma separated');
        $this->addOption('shallow-clone', null, null, 'Clone shallow');
        $this->addOption('with-openssl11', null, null, 'Use openssl 1.1');
        $this->addOption('with-php', null, InputOption::VALUE_REQUIRED, 'version in major.minor format (default 8.2)', '8.2');
        $this->addOption('clean', null, null, 'Clean old download cache and source before fetch');
        $this->addOption('all', 'A', null, 'Fetch all sources that static-php-cli needed');
        $this->addOption('custom-url', 'U', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Specify custom source download url, e.g "php-src:https://downloads.php.net/~eric/php-8.3.0beta1.tar.gz"');
        $this->addOption('from-zip', 'Z', InputOption::VALUE_REQUIRED, 'Fetch from zip archive');
        $this->addOption('for-extensions', 'e', InputOption::VALUE_REQUIRED, 'Fetch by extensions, e.g "openssl,mbstring"');
        $this->addOption('for-libs', null, InputOption::VALUE_REQUIRED, 'Fetch by libraries, e.g "libcares,openssl,onig"', null);
        $this->addOption('without-suggestions', null, null, 'Do not fetch suggested sources when using --for-extensions');
    }

    public function initialize(InputInterface $input, OutputInterface $output): void
    {
        if (
            $input->getOption('all')
            || $input->getOption('clean')
            || $input->getOption('from-zip')
            || $input->getOption('for-extensions')
            || $input->getOption('for-libs')
        ) {
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
        try {
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
                return static::FAILURE;
            }

            // --from-zip
            if ($path = $this->getOption('from-zip')) {
                return $this->downloadFromZip($path);
            }

            // Define PHP major version
            $ver = $this->php_major_ver = $this->getOption('with-php') ?? '8.1';
            define('SPC_BUILD_PHP_VERSION', $ver);
            preg_match('/^\d+\.\d+$/', $ver, $matches);
            if (!$matches) {
                logger()->error("bad version arg: {$ver}, x.y required!");
                return static::FAILURE;
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
                Config::$source['openssl']['regex'] = '/href="(?<file>openssl-(?<version>1.[^"]+)\.tar\.gz)\"/';
            }

            // --for-extensions
            if ($for_ext = $this->getOption('for-extensions')) {
                $ext = array_map('trim', array_filter(explode(',', $for_ext)));
                $sources = $this->calculateSourcesByExt($ext, !$this->getOption('without-suggestions'));
                array_unshift($sources, 'php-src', 'micro', 'pkg-config');
            } elseif ($for_lib = $this->getOption('for-libs')) {
                $lib = array_map('trim', array_filter(explode(',', $for_lib)));
                $sources = $this->calculateSourcesByLib($lib, !$this->getOption('without-suggestions'));
            } else {
                // get source list that will be downloaded
                $sources = array_map('trim', array_filter(explode(',', $this->getArgument('sources'))));
                if (empty($sources)) {
                    logger()->notice('Downloading with --all option will take more times to download, we recommend you to download with --for-extensions option !');
                    $sources = array_keys(Config::getSources());
                }
            }
            $chosen_sources = $sources;

            // Process -U options
            $custom_urls = [];
            foreach ($this->input->getOption('custom-url') as $value) {
                [$source_name, $url] = explode(':', $value, 2);
                $custom_urls[$source_name] = $url;
            }

            // Download them
            f_mkdir(DOWNLOAD_PATH);
            $cnt = count($chosen_sources);
            $ni = 0;
            foreach ($chosen_sources as $source) {
                ++$ni;
                if (isset($custom_urls[$source])) {
                    $config = Config::getSource($source);
                    $new_config = [
                        'type' => 'url',
                        'url' => $custom_urls[$source],
                    ];
                    if (isset($config['path'])) {
                        $new_config['path'] = $config['path'];
                    }
                    if (isset($config['filename'])) {
                        $new_config['filename'] = $config['filename'];
                    }
                    logger()->info("Fetching source {$source} from custom url [{$ni}/{$cnt}]");
                    Downloader::downloadSource($source, $new_config, true);
                } else {
                    logger()->info("Fetching source {$source} [{$ni}/{$cnt}]");
                    Downloader::downloadSource($source, Config::getSource($source));
                }
            }
            $time = round(microtime(true) - START_TIME, 3);
            logger()->info('Download complete, used ' . $time . ' s !');
            return static::SUCCESS;
        } catch (DownloaderException $e) {
            logger()->error($e->getMessage());
            return static::FAILURE;
        } catch (WrongUsageException $e) {
            logger()->critical($e->getMessage());
            return static::FAILURE;
        }
    }

    private function downloadFromZip(string $path): int
    {
        if (!file_exists($path)) {
            logger()->critical('File ' . $path . ' not exist or not a zip archive.');
            return static::FAILURE;
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
            return static::FAILURE;
        }
        // create downloads
        try {
            if (PHP_OS_FAMILY !== 'Windows') {
                $abs_path = realpath($path);
                f_passthru('mkdir ' . DOWNLOAD_PATH . ' && cd ' . DOWNLOAD_PATH . ' && unzip ' . escapeshellarg($abs_path));
            }
            // Windows TODO

            if (!file_exists(DOWNLOAD_PATH . '/.lock.json')) {
                throw new RuntimeException('.lock.json not exist in "downloads/"');
            }
        } catch (RuntimeException $e) {
            logger()->critical('Extract failed: ' . $e->getMessage());
            return static::FAILURE;
        }
        logger()->info('Extract success');
        return static::SUCCESS;
    }

    /**
     * Calculate the sources by extensions
     *
     * @param  array               $extensions       extension list
     * @param  bool                $include_suggests include suggested libs and extensions (default: true)
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    private function calculateSourcesByExt(array $extensions, bool $include_suggests = true): array
    {
        [$extensions, $libraries] = $include_suggests ? DependencyUtil::getExtsAndLibs($extensions, [], true, true) : DependencyUtil::getExtsAndLibs($extensions);
        $sources = [];
        foreach ($extensions as $extension) {
            if (Config::getExt($extension, 'type') === 'external') {
                $sources[] = Config::getExt($extension, 'source');
            }
        }
        foreach ($libraries as $library) {
            $sources[] = Config::getLib($library, 'source');
        }
        return array_values(array_unique($sources));
    }

    /**
     * Calculate the sources by libraries
     *
     * @param  array               $libs             library list
     * @param  bool                $include_suggests include suggested libs (default: true)
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    private function calculateSourcesByLib(array $libs, bool $include_suggests = true): array
    {
        $libs = DependencyUtil::getLibs($libs, $include_suggests);
        $sources = [];
        foreach ($libs as $library) {
            $sources[] = Config::getLib($library, 'source');
        }
        return array_values(array_unique($sources));
    }
}
