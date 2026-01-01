<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\builder\traits\UnixSystemUtilTrait;
use SPC\exception\DownloaderException;
use SPC\exception\SPCException;
use SPC\store\Config;
use SPC\store\Downloader;
use SPC\store\FileSystem;
use SPC\store\LockFile;
use SPC\store\source\CustomSourceBase;
use SPC\util\DependencyUtil;
use SPC\util\SPCTarget;
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
        $this->addArgument('sources', InputArgument::OPTIONAL, 'The sources will be compiled, comma separated');
        $this->addOption('shallow-clone', null, null, 'Clone shallow');
        $this->addOption('with-openssl11', null, null, 'Use openssl 1.1');
        $this->addOption('with-php', null, InputOption::VALUE_REQUIRED, 'version in major.minor format, comma-separated for multiple versions (default 8.4)', '8.4');
        $this->addOption('clean', null, null, 'Clean old download cache and source before fetch');
        $this->addOption('all', 'A', null, 'Fetch all sources that static-php-cli needed');
        $this->addOption('custom-url', 'U', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Specify custom source download url, e.g "php-src:https://downloads.php.net/~eric/php-8.3.0beta1.tar.gz"');
        $this->addOption('custom-git', 'G', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Specify custom source git url, e.g "php-src:master:https://github.com/php/php-src.git"');
        $this->addOption('from-zip', 'Z', InputOption::VALUE_REQUIRED, 'Fetch from zip archive');
        $this->addOption('for-extensions', 'e', InputOption::VALUE_REQUIRED, 'Fetch by extensions, e.g "openssl,mbstring"');
        $this->addOption('for-libs', 'l', InputOption::VALUE_REQUIRED, 'Fetch by libraries, e.g "libcares,openssl,onig"');
        $this->addOption('without-suggestions', null, null, 'Do not fetch suggested sources when using --for-extensions');
        $this->addOption('ignore-cache-sources', null, InputOption::VALUE_OPTIONAL, 'Ignore some source caches, comma separated, e.g "php-src,curl,openssl"', false);
        $this->addOption('retry', 'R', InputOption::VALUE_REQUIRED, 'Set retry time when downloading failed (default: 0)', '0');
        $this->addOption('prefer-pre-built', 'P', null, 'Download pre-built libraries when available');
        $this->addOption('no-alt', null, null, 'Do not download alternative sources');
        $this->addOption('update', null, null, 'Check and update downloaded sources');
    }

    public function initialize(InputInterface $input, OutputInterface $output): void
    {
        // mode: --update
        if ($input->getOption('update') && empty($input->getArgument('sources')) && empty($input->getOption('for-extensions')) && empty($input->getOption('for-libs'))) {
            if (!file_exists(LockFile::LOCK_FILE)) {
                parent::initialize($input, $output);
                return;
            }
            $lock_content = json_decode(file_get_contents(LockFile::LOCK_FILE), true);
            if (is_array($lock_content)) {
                // Filter out pre-built sources
                $sources_to_check = array_filter($lock_content, function ($name) {
                    return
                        !str_contains($name, '-Linux-') &&
                        !str_contains($name, '-Windows-') &&
                        !str_contains($name, '-Darwin-');
                });
                $input->setArgument('sources', implode(',', array_keys($sources_to_check)));
            }
            parent::initialize($input, $output);
            return;
        }
        // mode: --all
        if ($input->getOption('all')) {
            $input->setArgument('sources', implode(',', array_keys(Config::getSources())));
            parent::initialize($input, $output);
            return;
        }
        // mode: --clean and --from-zip
        if ($input->getOption('clean') || $input->getOption('from-zip')) {
            $input->setArgument('sources', '');
            parent::initialize($input, $output);
            return;
        }
        // mode: normal
        if (!empty($input->getArgument('sources'))) {
            $final_sources = array_map('trim', array_filter(explode(',', $input->getArgument('sources'))));
        } else {
            $final_sources = [];
        }
        // mode: --for-extensions
        if ($for_ext = $input->getOption('for-extensions')) {
            $ext = $this->parseExtensionList($for_ext);
            $sources = $this->calculateSourcesByExt($ext, !$input->getOption('without-suggestions'));
            $final_sources = array_merge($final_sources, array_diff($sources, $final_sources));
        }
        // mode: --for-libs
        if ($for_lib = $input->getOption('for-libs')) {
            $lib = array_map('trim', array_filter(explode(',', $for_lib)));
            $sources = $this->calculateSourcesByLib($lib, !$input->getOption('without-suggestions'));
            $final_sources = array_merge($final_sources, array_diff($sources, $final_sources));
        }
        if (!empty($final_sources)) {
            $input->setArgument('sources', implode(',', $final_sources));
        }
        parent::initialize($input, $output);
    }

    public function handle(): int
    {
        if ($this->getOption('clean')) {
            return $this->_clean();
        }

        // --from-zip
        if ($path = $this->getOption('from-zip')) {
            return $this->downloadFromZip($path);
        }

        if ($this->getOption('update')) {
            return $this->handleUpdate();
        }

        // Define PHP major version(s)
        $php_versions_str = $this->getOption('with-php');
        $php_versions = array_map('trim', explode(',', $php_versions_str));

        // Validate all versions
        foreach ($php_versions as $ver) {
            if ($ver !== 'git' && !preg_match('/^\d+\.\d+$/', $ver)) {
                // If not git, we need to check the version format
                if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $ver)) {
                    logger()->error("bad version arg: {$ver}, x.y or x.y.z required!");
                    return static::FAILURE;
                }
            }
        }

        // Set the first version as the default for backward compatibility
        $this->php_major_ver = $php_versions[0];
        define('SPC_BUILD_PHP_VERSION', $this->php_major_ver);

        // retry
        $retry = (int) $this->getOption('retry');
        f_putenv('SPC_DOWNLOAD_RETRIES=' . $retry);

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

        $chosen_sources = array_map('trim', array_filter(explode(',', $this->getArgument('sources'))));

        // Handle multiple PHP versions
        // If php-src is in the sources, replace it with version-specific sources
        if (in_array('php-src', $chosen_sources)) {
            // Remove php-src from the list
            $chosen_sources = array_diff($chosen_sources, ['php-src']);
            // Add version-specific php-src for each version
            foreach ($php_versions as $ver) {
                $version_specific_name = "php-src-{$ver}";
                $chosen_sources[] = $version_specific_name;
                // Store the version for this specific php-src
                f_putenv("SPC_PHP_VERSION_{$version_specific_name}={$ver}");
            }
        }

        $sss = $this->getOption('ignore-cache-sources');
        if ($sss === false) {
            // false is no-any-ignores, that is, default.
            $force_all = false;
            $force_list = [];
        } elseif ($sss === null) {
            // null means all sources will be ignored, equals to --force-all (but we don't want to add too many options)
            $force_all = true;
            $force_list = [];
        } else {
            // ignore some sources
            $force_all = false;
            $force_list = array_map('trim', array_filter(explode(',', $this->getOption('ignore-cache-sources'))));
        }

        if ($this->getOption('all')) {
            logger()->notice('Downloading with --all option will take more times to download, we recommend you to download with --for-extensions option !');
        }

        // Process -U options
        $custom_urls = [];
        foreach ($this->input->getOption('custom-url') as $value) {
            [$source_name, $url] = explode(':', $value, 2);
            $custom_urls[$source_name] = $url;
        }
        // Process -G options
        $custom_gits = [];
        foreach ($this->input->getOption('custom-git') as $value) {
            [$source_name, $branch, $url] = explode(':', $value, 3);
            $custom_gits[$source_name] = [$branch, $url];
        }

        // If passing --prefer-pre-built option, we need to load pre-built library list from pre-built.json targeted releases
        if ($this->getOption('prefer-pre-built')) {
            $repo = Config::getPreBuilt('repo');
            $pre_built_libs = Downloader::getLatestGithubRelease($repo, [
                'repo' => $repo,
                'prefer-stable' => Config::getPreBuilt('prefer-stable'),
            ], false);
        } else {
            $pre_built_libs = [];
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
                logger()->info("[{$ni}/{$cnt}] Downloading source {$source} from custom url: {$new_config['url']}");
                Downloader::downloadSource($source, $new_config, true);
            } elseif (isset($custom_gits[$source])) {
                $config = Config::getSource($source);
                $new_config = [
                    'type' => 'git',
                    'rev' => $custom_gits[$source][0],
                    'url' => $custom_gits[$source][1],
                ];
                if (isset($config['path'])) {
                    $new_config['path'] = $config['path'];
                }
                logger()->info("[{$ni}/{$cnt}] Downloading source {$source} from custom git: {$new_config['url']}");
                Downloader::downloadSource($source, $new_config, true);
            } else {
                // Handle version-specific php-src (php-src-8.2, php-src-8.3, etc.)
                if (preg_match('/^php-src-[\d.]+$/', $source)) {
                    $config = Config::getSource('php-src');
                    if ($config === null) {
                        logger()->error('php-src configuration not found in source.json');
                        return static::FAILURE;
                    }
                } else {
                    $config = Config::getSource($source);
                }
                // Prefer pre-built, we need to search pre-built library
                if ($this->getOption('prefer-pre-built') && ($config['provide-pre-built'] ?? false) === true) {
                    // We need to replace pattern
                    $replace = [
                        '{name}' => $source,
                        '{arch}' => arch2gnu(php_uname('m')),
                        '{os}' => strtolower(PHP_OS_FAMILY),
                        '{libc}' => SPCTarget::getLibc() ?? 'default',
                        '{libcver}' => SPCTarget::getLibcVersion() ?? 'default',
                    ];
                    $find = str_replace(array_keys($replace), array_values($replace), Config::getPreBuilt('match-pattern'));
                    // find filename in asset list
                    if (($url = $this->findPreBuilt($pre_built_libs, $find)) !== null) {
                        logger()->info("[{$ni}/{$cnt}] Downloading pre-built content {$source}");
                        Downloader::downloadSource($source, ['type' => 'url', 'url' => $url], $force_all || in_array($source, $force_list), SPC_DOWNLOAD_PRE_BUILT);
                        continue;
                    }
                    logger()->warning("Pre-built content not found for {$source}, fallback to source download");
                }
                logger()->info("[{$ni}/{$cnt}] Downloading source {$source}");
                try {
                    Downloader::downloadSource($source, $config, $force_all || in_array($source, $force_list));
                } catch (SPCException $e) {
                    // if `--no-alt` option is set, we will not download alternative sources
                    if ($this->getOption('no-alt')) {
                        throw $e;
                    }
                    // if download failed, we will try to download alternative sources
                    logger()->warning("Download failed: {$e->getMessage()}");
                    $alt_sources = Config::getSource($source)['alt'] ?? null;
                    if ($alt_sources === null) {
                        logger()->warning("No alternative sources found for {$source}, using default alternative source");
                        $alt_config = array_merge($config, Downloader::getDefaultAlternativeSource($source));
                    } elseif ($alt_sources === false) {
                        throw new DownloaderException("No alternative sources found for {$source}, skipping alternative download");
                    } else {
                        logger()->notice("Trying to download alternative sources for {$source}");
                        $alt_config = array_merge($config, $alt_sources);
                    }
                    Downloader::downloadSource($source, $alt_config, $force_all || in_array($source, $force_list));
                }
            }
        }
        $time = round(microtime(true) - START_TIME, 3);
        logger()->info('Download complete, used ' . $time . ' s !');
        return static::SUCCESS;
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
        if (PHP_OS_FAMILY !== 'Windows' && !self::findCommand('unzip')) {
            $this->output->writeln('Missing unzip command, you need to install it first !');
            $this->output->writeln('You can use "bin/spc doctor" command to check and install required tools');
            return static::FAILURE;
        }
        // create downloads
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows TODO
            $this->output->writeln('<error>Windows currently does not support --from-zip !</error>');
            return static::FAILURE;
        }
        $abs_path = realpath($path);
        f_passthru('mkdir ' . DOWNLOAD_PATH . ' && cd ' . DOWNLOAD_PATH . ' && unzip ' . escapeshellarg($abs_path));

        if (!file_exists(LockFile::LOCK_FILE)) {
            $this->output->writeln('<error>.lock.json not exist in "downloads/", please run "bin/spc download" first !</error>');
            return static::FAILURE;
        }
        $this->output->writeln('<info>Extract success</info>');
        return static::SUCCESS;
    }

    /**
     * Calculate the sources by extensions
     *
     * @param array $extensions       extension list
     * @param bool  $include_suggests include suggested libs and extensions (default: true)
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
            $source = Config::getLib($library, 'source');
            if ($source !== null) {
                $sources[] = $source;
            }
        }
        return array_values(array_unique($sources));
    }

    /**
     * Calculate the sources by libraries
     *
     * @param array $libs             library list
     * @param bool  $include_suggests include suggested libs (default: true)
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

    /**
     * @param  array       $assets   Asset list from GitHub API
     * @param  string      $filename Match file name, e.g. pkg-config-aarch64-darwin.txz
     * @return null|string Return the download URL if found, otherwise null
     */
    private function findPreBuilt(array $assets, string $filename): ?string
    {
        logger()->debug("Finding pre-built asset {$filename}");
        foreach ($assets as $asset) {
            if ($asset['name'] === $filename) {
                return $asset['browser_download_url'];
            }
        }
        return null;
    }

    private function _clean(): int
    {
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

    private function handleUpdate(): int
    {
        logger()->info('Checking sources for updates...');

        // Get lock file content
        $lock_file_path = LockFile::LOCK_FILE;
        if (!file_exists($lock_file_path)) {
            logger()->warning('No lock file found. Please download sources first using "bin/spc download"');
            return static::FAILURE;
        }

        $lock_content = json_decode(file_get_contents($lock_file_path), true);
        if ($lock_content === null || !is_array($lock_content)) {
            logger()->error('Failed to parse lock file');
            return static::FAILURE;
        }

        // Filter sources to check
        $sources_arg = $this->getArgument('sources');
        if (!empty($sources_arg)) {
            $requested_sources = array_map('trim', array_filter(explode(',', $sources_arg)));
            $sources_to_check = [];
            foreach ($requested_sources as $source) {
                if (isset($lock_content[$source])) {
                    $sources_to_check[$source] = $lock_content[$source];
                } else {
                    logger()->warning("Source '{$source}' not found in lock file, skipping");
                }
            }
        } else {
            $sources_to_check = $lock_content;
        }

        // Filter out pre-built sources (they are derivatives)
        $sources_to_check = array_filter($sources_to_check, function ($lock_item, $name) {
            // Skip pre-built sources (they contain OS/arch in the name)
            if (str_contains($name, '-Linux-') || str_contains($name, '-Windows-') || str_contains($name, '-Darwin-')) {
                logger()->debug("Skipping pre-built source: {$name}");
                return false;
            }
            return true;
        }, ARRAY_FILTER_USE_BOTH);

        if (empty($sources_to_check)) {
            logger()->warning('No sources to check');
            return static::FAILURE;
        }

        $total = count($sources_to_check);
        $current = 0;
        $updated_sources = [];

        foreach ($sources_to_check as $name => $lock_item) {
            ++$current;
            try {
                // Handle version-specific php-src (php-src-8.2, php-src-8.3, etc.)
                if (preg_match('/^php-src-[\d.]+$/', $name)) {
                    $config = Config::getSource('php-src');
                } else {
                    $config = Config::getSource($name);
                }

                if ($config === null) {
                    logger()->warning("[{$current}/{$total}] Source '{$name}' not found in source config, skipping");
                    continue;
                }

                // Check and update based on source type
                $source_type = $lock_item['source_type'] ?? 'unknown';

                if ($source_type === SPC_SOURCE_ARCHIVE) {
                    if ($this->checkArchiveSourceUpdate($name, $lock_item, $config, $current, $total)) {
                        $updated_sources[] = $name;
                    }
                } elseif ($source_type === SPC_SOURCE_GIT) {
                    if ($this->checkGitSourceUpdate($name, $lock_item, $config, $current, $total)) {
                        $updated_sources[] = $name;
                    }
                } elseif ($source_type === SPC_SOURCE_LOCAL) {
                    logger()->debug("[{$current}/{$total}] Source '{$name}' is local, skipping");
                } else {
                    logger()->warning("[{$current}/{$total}] Unknown source type '{$source_type}' for '{$name}', skipping");
                }
            } catch (\Throwable $e) {
                logger()->error("[{$current}/{$total}] Error checking '{$name}': {$e->getMessage()}");
                continue;
            }
        }

        // Output summary
        if (empty($updated_sources)) {
            logger()->info('All sources are up to date.');
        } else {
            logger()->info('Updated sources: ' . implode(', ', $updated_sources));

            // Write updated sources to file
            $date = date('Y-m-d');
            $update_file = DOWNLOAD_PATH . '/.update-' . $date . '.txt';
            $content = implode(',', $updated_sources);
            file_put_contents($update_file, $content);
            logger()->debug("Updated sources written to: {$update_file}");
        }

        return static::SUCCESS;
    }

    private function checkCustomSourceUpdate(string $name, array $lock, array $config, int $current, int $total): bool
    {
        $classes = FileSystem::getClassesPsr4(ROOT_DIR . '/src/SPC/store/source', 'SPC\store\source');
        foreach ($classes as $class) {
            // Support php-src and php-src-X.Y patterns
            $matches = ($class::NAME === $name) ||
                ($class::NAME === 'php-src' && preg_match('/^php-src(-[\d.]+)?$/', $name));
            if (is_a($class, CustomSourceBase::class, true) && $matches) {
                try {
                    $config['source_name'] = $name;
                    $updated = (new $class())->update($lock, $config);
                    if ($updated) {
                        logger()->info("[{$current}/{$total}] Source '{$name}' updated");
                    } else {
                        logger()->info("[{$current}/{$total}] Source '{$name}' is up to date");
                    }
                    return $updated;
                } catch (\Throwable $e) {
                    logger()->warning("[{$current}/{$total}] Failed to check '{$name}': {$e->getMessage()}");
                    return false;
                }
            }
        }
        logger()->warning("[{$current}/{$total}] Custom source handler for '{$name}' not found");
        return false;
    }

    /**
     * Check and update an archive source
     *
     * @param  string $name    Source name
     * @param  array  $lock    Lock file entry
     * @param  array  $config  Source configuration
     * @param  int    $current Current progress number
     * @param  int    $total   Total sources to check
     * @return bool   True if updated, false otherwise
     */
    private function checkArchiveSourceUpdate(string $name, array $lock, array $config, int $current, int $total): bool
    {
        $type = $config['type'] ?? 'unknown';
        $locked_filename = $lock['filename'] ?? '';

        // Skip local types that don't support version detection
        if (in_array($type, ['url', 'local', 'unknown'])) {
            logger()->debug("[{$current}/{$total}] Source '{$name}' (type: {$type}) doesn't support version detection, skipping");
            return false;
        }

        try {
            // Get latest version info
            $latest_info = match ($type) {
                'ghtar' => Downloader::getLatestGithubTarball($name, $config),
                'ghtagtar' => Downloader::getLatestGithubTarball($name, $config, 'tags'),
                'ghrel' => Downloader::getLatestGithubRelease($name, $config),
                'pie' => Downloader::getPIEInfo($name, $config),
                'bitbuckettag' => Downloader::getLatestBitbucketTag($name, $config),
                'filelist' => Downloader::getFromFileList($name, $config),
                'url' => Downloader::getLatestUrlInfo($name, $config),
                'custom' => $this->checkCustomSourceUpdate($name, $lock, $config, $current, $total),
                default => null,
            };

            if ($latest_info === null) {
                logger()->warning("[{$current}/{$total}] Could not get version info for '{$name}' (type: {$type})");
                return false;
            }

            $latest_filename = $latest_info[1] ?? '';

            // Compare filenames
            if ($locked_filename !== $latest_filename) {
                logger()->info("[{$current}/{$total}] Update available for '{$name}': {$locked_filename} → {$latest_filename}");
                $this->downloadSourceForUpdate($name, $config, $current, $total);
                return true;
            }

            logger()->info("[{$current}/{$total}] Source '{$name}' is up to date");
            return false;
        } catch (DownloaderException $e) {
            logger()->warning("[{$current}/{$total}] Failed to check '{$name}': {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Check and update a git source
     *
     * @param  string $name    Source name
     * @param  array  $lock    Lock file entry
     * @param  array  $config  Source configuration
     * @param  int    $current Current progress number
     * @param  int    $total   Total sources to check
     * @return bool   True if updated, false otherwise
     */
    private function checkGitSourceUpdate(string $name, array $lock, array $config, int $current, int $total): bool
    {
        $locked_hash = $lock['hash'] ?? '';
        $url = $config['url'] ?? '';
        $branch = $config['rev'] ?? 'main';

        if (empty($url)) {
            logger()->warning("[{$current}/{$total}] No URL found for git source '{$name}'");
            return false;
        }

        try {
            $remote_hash = $this->getRemoteGitCommit($url, $branch);

            if ($remote_hash === null) {
                logger()->warning("[{$current}/{$total}] Could not fetch remote commit for '{$name}'");
                return false;
            }

            // Compare hashes (use first 7 chars for display)
            $locked_short = substr($locked_hash, 0, 7);
            $remote_short = substr($remote_hash, 0, 7);

            if ($locked_hash !== $remote_hash) {
                logger()->info("[{$current}/{$total}] Update available for '{$name}': {$locked_short} → {$remote_short}");
                $this->downloadSourceForUpdate($name, $config, $current, $total);
                return true;
            }

            logger()->info("[{$current}/{$total}] Source '{$name}' is up to date");
            return false;
        } catch (\Throwable $e) {
            logger()->warning("[{$current}/{$total}] Failed to check '{$name}': {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Download a source after removing old lock entry
     *
     * @param string $name    Source name
     * @param array  $config  Source configuration
     * @param int    $current Current progress number
     * @param int    $total   Total sources to check
     */
    private function downloadSourceForUpdate(string $name, array $config, int $current, int $total): void
    {
        logger()->info("[{$current}/{$total}] Downloading '{$name}'...");

        // Remove old lock entry (this triggers cleanup of old files)
        LockFile::put($name, null);

        // Download new version
        Downloader::downloadSource($name, $config, true);
    }

    /**
     * Get remote git commit hash without cloning
     *
     * @param  string      $url    Git repository URL
     * @param  string      $branch Branch or tag to check
     * @return null|string Remote commit hash or null on failure
     */
    private function getRemoteGitCommit(string $url, string $branch): ?string
    {
        try {
            $cmd = SPC_GIT_EXEC . ' ls-remote ' . escapeshellarg($url) . ' ' . escapeshellarg($branch);
            f_exec($cmd, $output, $ret);

            if ($ret !== 0 || empty($output)) {
                return null;
            }

            // Output format: "commit_hash\trefs/heads/branch" or "commit_hash\tHEAD"
            $parts = preg_split('/\s+/', $output[0]);
            return $parts[0] ?? null;
        } catch (\Throwable $e) {
            logger()->debug("Failed to fetch remote git commit: {$e->getMessage()}");
            return null;
        }
    }
}
