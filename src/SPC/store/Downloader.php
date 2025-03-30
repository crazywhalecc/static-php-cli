<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\builder\linux\SystemUtil;
use SPC\exception\DownloaderException;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\source\CustomSourceBase;

/**
 * Source Downloader.
 */
class Downloader
{
    /**
     * Get latest version from BitBucket tag (type = bitbuckettag)
     *
     * @param  string              $name   source name
     * @param  array               $source source meta info: [repo]
     * @return array<int, string>  [url, filename]
     * @throws DownloaderException
     */
    public static function getLatestBitbucketTag(string $name, array $source): array
    {
        logger()->debug("finding {$name} source from bitbucket tag");
        $data = json_decode(self::curlExec(
            url: "https://api.bitbucket.org/2.0/repositories/{$source['repo']}/refs/tags",
            retry: self::getRetryTime()
        ), true);
        $ver = $data['values'][0]['name'];
        if (!$ver) {
            throw new DownloaderException("failed to find {$name} bitbucket source");
        }
        $url = "https://bitbucket.org/{$source['repo']}/get/{$ver}.tar.gz";
        $headers = self::curlExec(
            url: $url,
            method: 'HEAD',
            retry: self::getRetryTime()
        );
        preg_match('/^content-disposition:\s+attachment;\s*filename=("?)(?<filename>.+\.tar\.gz)\1/im', $headers, $matches);
        if ($matches) {
            $filename = $matches['filename'];
        } else {
            $filename = "{$name}-{$data['tag_name']}.tar.gz";
        }

        return [$url, $filename];
    }

    /**
     * Get latest version from GitHub tarball (type = ghtar / ghtagtar)
     *
     * @param  string             $name   source name
     * @param  array              $source source meta info: [repo]
     * @param  string             $type   type of tarball, default is 'releases'
     * @return array<int, string> [url, filename]
     *
     * @throws DownloaderException
     */
    public static function getLatestGithubTarball(string $name, array $source, string $type = 'releases'): array
    {
        logger()->debug("finding {$name} source from github {$type} tarball");
        $data = json_decode(self::curlExec(
            url: "https://api.github.com/repos/{$source['repo']}/{$type}",
            hooks: [[CurlHook::class, 'setupGithubToken']],
            retry: self::getRetryTime()
        ), true);

        $url = null;
        for ($i = 0; $i < count($data); ++$i) {
            if (($data[$i]['prerelease'] ?? false) === true && ($source['prefer-stable'] ?? false)) {
                continue;
            }
            if (!($source['match'] ?? null)) {
                $url = $data[$i]['tarball_url'] ?? null;
                break;
            }
            if (preg_match('|' . $source['match'] . '|', $data[$i]['tarball_url'])) {
                $url = $data[$i]['tarball_url'];
                break;
            }
        }
        if (!$url) {
            throw new DownloaderException("failed to find {$name} source");
        }
        $headers = self::curlExec(
            url: $url,
            method: 'HEAD',
            hooks: [[CurlHook::class, 'setupGithubToken']],
            retry: self::getRetryTime()
        );
        preg_match('/^content-disposition:\s+attachment;\s*filename=("?)(?<filename>.+\.tar\.gz)\1/im', $headers, $matches);
        if ($matches) {
            $filename = $matches['filename'];
        } else {
            $filename = "{$name}-" . ($type === 'releases' ? $data['tag_name'] : $data['name']) . '.tar.gz';
        }

        return [$url, $filename];
    }

    /**
     * Get latest version from GitHub release (uploaded archive)
     *
     * @param  string              $name         source name
     * @param  array               $source       source meta info: [repo, match]
     * @param  bool                $match_result Whether to return matched result by `match` param (default: true)
     * @return array<int, string>  When $match_result = true, and we matched, [url, filename]. Otherwise, [{asset object}. ...]
     * @throws DownloaderException
     */
    public static function getLatestGithubRelease(string $name, array $source, bool $match_result = true): array
    {
        logger()->debug("finding {$name} from github releases assets");
        $data = json_decode(self::curlExec(
            url: "https://api.github.com/repos/{$source['repo']}/releases",
            hooks: [[CurlHook::class, 'setupGithubToken']],
            retry: self::getRetryTime()
        ), true);
        $url = null;
        foreach ($data as $release) {
            if (($source['prefer-stable'] ?? false) === true && $release['prerelease'] === true) {
                continue;
            }
            logger()->debug("Found {$release['name']} releases assets");
            if (!$match_result) {
                return $release['assets'];
            }
            foreach ($release['assets'] as $asset) {
                if (preg_match('|' . $source['match'] . '|', $asset['name'])) {
                    $url = $asset['browser_download_url'];
                    break 2;
                }
            }
        }

        if (!$url) {
            throw new DownloaderException("failed to find {$name} release metadata");
        }
        $filename = basename($url);

        return [$url, $filename];
    }

    /**
     * Get latest version from file list (regex based crawler)
     *
     * @param  string              $name   source name
     * @param  array               $source source meta info: [url, regex]
     * @return array<int, string>  [url, filename]
     * @throws DownloaderException
     */
    public static function getFromFileList(string $name, array $source): array
    {
        logger()->debug("finding {$name} source from file list");
        $page = self::curlExec($source['url'], retry: self::getRetryTime());
        preg_match_all($source['regex'], $page, $matches);
        if (!$matches) {
            throw new DownloaderException("Failed to get {$name} version");
        }
        $versions = [];
        foreach ($matches['version'] as $i => $version) {
            $lowerVersion = strtolower($version);
            foreach ([
                'alpha',
                'beta',
                'rc',
                'pre',
                'nightly',
                'snapshot',
                'dev',
            ] as $betaVersion) {
                if (str_contains($lowerVersion, $betaVersion)) {
                    continue 2;
                }
            }
            $versions[$version] = $matches['file'][$i];
        }
        uksort($versions, 'version_compare');

        return [$source['url'] . end($versions), end($versions), key($versions)];
    }

    /**
     * Just download file using system curl command, and lock it
     *
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public static function downloadFile(string $name, string $url, string $filename, ?string $move_path = null, int $download_as = SPC_DOWNLOAD_SOURCE): void
    {
        logger()->debug("Downloading {$url}");
        $cancel_func = function () use ($filename) {
            if (file_exists(FileSystem::convertPath(DOWNLOAD_PATH . '/' . $filename))) {
                logger()->warning('Deleting download file: ' . $filename);
                unlink(FileSystem::convertPath(DOWNLOAD_PATH . '/' . $filename));
            }
        };
        self::registerCancelEvent($cancel_func);
        self::curlDown(url: $url, path: FileSystem::convertPath(DOWNLOAD_PATH . "/{$filename}"), retry: self::getRetryTime());
        self::unregisterCancelEvent();
        logger()->debug("Locking {$filename}");
        if ($download_as === SPC_DOWNLOAD_PRE_BUILT) {
            $name = self::getPreBuiltLockName($name);
        }
        self::lockSource($name, ['source_type' => 'archive', 'filename' => $filename, 'move_path' => $move_path, 'lock_as' => $download_as]);
    }

    /**
     * Try to lock source.
     *
     * @param string $name Source name
     * @param array{
     *     source_type: string,
     *     dirname: ?string,
     *     filename: ?string,
     *     move_path: ?string,
     *     lock_as: int
     * } $data Source data
     * @throws FileSystemException
     */
    public static function lockSource(string $name, array $data): void
    {
        if (!file_exists(FileSystem::convertPath(DOWNLOAD_PATH . '/.lock.json'))) {
            $lock = [];
        } else {
            $lock = json_decode(FileSystem::readFile(DOWNLOAD_PATH . '/.lock.json'), true) ?? [];
        }
        $lock[$name] = $data;
        FileSystem::writeFile(DOWNLOAD_PATH . '/.lock.json', json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Download git source, and lock it.
     *
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public static function downloadGit(string $name, string $url, string $branch, ?string $move_path = null, int $retry = 0, int $lock_as = SPC_DOWNLOAD_SOURCE): void
    {
        $download_path = FileSystem::convertPath(DOWNLOAD_PATH . "/{$name}");
        if (file_exists($download_path)) {
            FileSystem::removeDir($download_path);
        }
        logger()->debug("cloning {$name} source");
        $check = !defined('DEBUG_MODE') ? ' -q' : '';
        $cancel_func = function () use ($download_path) {
            if (is_dir($download_path)) {
                logger()->warning('Removing path ' . $download_path);
                FileSystem::removeDir($download_path);
            }
        };
        try {
            self::registerCancelEvent($cancel_func);
            f_passthru(
                SPC_GIT_EXEC . ' clone' . $check .
                (defined('DEBUG_MODE') ? '' : ' --quiet') .
                ' --config core.autocrlf=false ' .
                "--branch \"{$branch}\" " . (defined('GIT_SHALLOW_CLONE') ? '--depth 1 --single-branch' : '') . " --recursive \"{$url}\" \"{$download_path}\""
            );
        } catch (RuntimeException $e) {
            if ($e->getCode() === 2 || $e->getCode() === -1073741510) {
                throw new WrongUsageException('Keyboard interrupted, download failed !');
            }
            if ($retry > 0) {
                self::downloadGit($name, $url, $branch, $move_path, $retry - 1);
                return;
            }
            throw $e;
        } finally {
            self::unregisterCancelEvent();
        }
        // Lock
        logger()->debug("Locking git source {$name}");
        self::lockSource($name, ['source_type' => 'dir', 'dirname' => $name, 'move_path' => $move_path, 'lock_as' => $lock_as]);

        /*
        // 复制目录过去
        if ($path !== $download_path) {
            $dst_path = FileSystem::convertPath($path);
            $src_path = FileSystem::convertPath($download_path);
            switch (PHP_OS_FAMILY) {
                case 'Windows':
                    f_passthru('xcopy "' . $src_path . '" "' . $dst_path . '" /s/e/v/y/i');
                    break;
                case 'Linux':
                case 'Darwin':
                    f_passthru('cp -r "' . $src_path . '" "' . $dst_path . '"');
                    break;
            }
        }*/
    }

    /**
     * @param string $name Package name
     * @param null|array{
     *     type: string,
     *     repo: ?string,
     *     url: ?string,
     *     rev: ?string,
     *     path: ?string,
     *     filename: ?string,
     *     match: ?string,
     *     prefer-stable: ?bool,
     *     extract-files: ?array<string, string>
     * } $pkg Package config
     * @param  bool                $force Download all the time even if it exists
     * @throws DownloaderException
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    public static function downloadPackage(string $name, ?array $pkg = null, bool $force = false): void
    {
        if ($pkg === null) {
            $pkg = Config::getPkg($name);
        }

        if ($pkg === null) {
            logger()->warning('Package {name} unknown. Skipping.', ['name' => $name]);
            return;
        }

        if (!is_dir(DOWNLOAD_PATH)) {
            FileSystem::createDir(DOWNLOAD_PATH);
        }

        if (self::isAlreadyDownloaded($name, $force, SPC_DOWNLOAD_PACKAGE)) {
            return;
        }

        try {
            switch ($pkg['type']) {
                case 'bitbuckettag':    // BitBucket Tag
                    [$url, $filename] = self::getLatestBitbucketTag($name, $pkg);
                    self::downloadFile($name, $url, $filename, $pkg['extract'] ?? null, SPC_DOWNLOAD_PACKAGE);
                    break;
                case 'ghtar':           // GitHub Release (tar)
                    [$url, $filename] = self::getLatestGithubTarball($name, $pkg);
                    self::downloadFile($name, $url, $filename, $pkg['extract'] ?? null, SPC_DOWNLOAD_PACKAGE);
                    break;
                case 'ghtagtar':        // GitHub Tag (tar)
                    [$url, $filename] = self::getLatestGithubTarball($name, $pkg, 'tags');
                    self::downloadFile($name, $url, $filename, $pkg['extract'] ?? null, SPC_DOWNLOAD_PACKAGE);
                    break;
                case 'ghrel':           // GitHub Release (uploaded)
                    [$url, $filename] = self::getLatestGithubRelease($name, $pkg);
                    self::downloadFile($name, $url, $filename, $pkg['extract'] ?? null, SPC_DOWNLOAD_PACKAGE);
                    break;
                case 'filelist':        // Basic File List (regex based crawler)
                    [$url, $filename] = self::getFromFileList($name, $pkg);
                    self::downloadFile($name, $url, $filename, $pkg['extract'] ?? null, SPC_DOWNLOAD_PACKAGE);
                    break;
                case 'url':             // Direct download URL
                    $url = $pkg['url'];
                    $filename = $pkg['filename'] ?? basename($pkg['url']);
                    self::downloadFile($name, $url, $filename, $pkg['extract'] ?? null, SPC_DOWNLOAD_PACKAGE);
                    break;
                case 'git':             // Git repo
                    self::downloadGit(
                        $name,
                        $pkg['url'],
                        $pkg['rev'],
                        $pkg['extract'] ?? null,
                        self::getRetryTime(),
                        SPC_DOWNLOAD_PRE_BUILT
                    );
                    break;
                case 'custom':          // Custom download method, like API-based download or other
                    $classes = FileSystem::getClassesPsr4(ROOT_DIR . '/src/SPC/store/source', 'SPC\store\source');
                    foreach ($classes as $class) {
                        if (is_a($class, CustomSourceBase::class, true) && $class::NAME === $name) {
                            (new $class())->fetch($force);
                            break;
                        }
                    }
                    break;
                default:
                    throw new DownloaderException('unknown source type: ' . $pkg['type']);
            }
        } catch (RuntimeException $e) {
            // Because sometimes files downloaded through the command line are not automatically deleted after a failure.
            // Here we need to manually delete the file if it is detected to exist.
            if (isset($filename) && file_exists(DOWNLOAD_PATH . '/' . $filename)) {
                logger()->warning('Deleting download file: ' . $filename);
                unlink(DOWNLOAD_PATH . '/' . $filename);
            }
            throw new DownloaderException('Download failed! ' . $e->getMessage());
        }
    }

    /**
     * Download source by name and meta.
     *
     * @param string $name source name
     * @param  null|array{
     *     type: string,
     *     repo: ?string,
     *     url: ?string,
     *     rev: ?string,
     *     path: ?string,
     *     filename: ?string,
     *     match: ?string,
     *     prefer-stable: ?bool,
     *     provide-pre-built: ?bool,
     *     license: array{
     *         type: string,
     *         path: ?string,
     *         text: ?string
     *     }
     * }          $source  source meta info: [type, path, rev, url, filename, regex, license]
     * @param  bool                $force       Whether to force download (default: false)
     * @param  int                 $download_as Lock source type (default: SPC_LOCK_SOURCE)
     * @throws DownloaderException
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    public static function downloadSource(string $name, ?array $source = null, bool $force = false, int $download_as = SPC_DOWNLOAD_SOURCE): void
    {
        if ($source === null) {
            $source = Config::getSource($name);
        }

        if ($source === null) {
            logger()->warning('Source {name} unknown. Skipping.', ['name' => $name]);
            return;
        }

        if (!is_dir(DOWNLOAD_PATH)) {
            FileSystem::createDir(DOWNLOAD_PATH);
        }

        // load lock file
        if (self::isAlreadyDownloaded($name, $force, $download_as)) {
            return;
        }

        try {
            switch ($source['type']) {
                case 'bitbuckettag':    // BitBucket Tag
                    [$url, $filename] = self::getLatestBitbucketTag($name, $source);
                    self::downloadFile($name, $url, $filename, $source['path'] ?? null, $download_as);
                    break;
                case 'ghtar':           // GitHub Release (tar)
                    [$url, $filename] = self::getLatestGithubTarball($name, $source);
                    self::downloadFile($name, $url, $filename, $source['path'] ?? null, $download_as);
                    break;
                case 'ghtagtar':        // GitHub Tag (tar)
                    [$url, $filename] = self::getLatestGithubTarball($name, $source, 'tags');
                    self::downloadFile($name, $url, $filename, $source['path'] ?? null, $download_as);
                    break;
                case 'ghrel':           // GitHub Release (uploaded)
                    [$url, $filename] = self::getLatestGithubRelease($name, $source);
                    self::downloadFile($name, $url, $filename, $source['path'] ?? null, $download_as);
                    break;
                case 'filelist':        // Basic File List (regex based crawler)
                    [$url, $filename] = self::getFromFileList($name, $source);
                    self::downloadFile($name, $url, $filename, $source['path'] ?? null, $download_as);
                    break;
                case 'url':             // Direct download URL
                    $url = $source['url'];
                    $filename = $source['filename'] ?? basename($source['url']);
                    self::downloadFile($name, $url, $filename, $source['path'] ?? null, $download_as);
                    break;
                case 'git':             // Git repo
                    self::downloadGit(
                        $name,
                        $source['url'],
                        $source['rev'],
                        $source['path'] ?? null,
                        self::getRetryTime(),
                        $download_as
                    );
                    break;
                case 'custom':          // Custom download method, like API-based download or other
                    $classes = FileSystem::getClassesPsr4(ROOT_DIR . '/src/SPC/store/source', 'SPC\store\source');
                    foreach ($classes as $class) {
                        if (is_a($class, CustomSourceBase::class, true) && $class::NAME === $name) {
                            (new $class())->fetch($force, $source, $download_as);
                            break;
                        }
                    }
                    break;
                default:
                    throw new DownloaderException('unknown source type: ' . $source['type']);
            }
        } catch (RuntimeException $e) {
            // Because sometimes files downloaded through the command line are not automatically deleted after a failure.
            // Here we need to manually delete the file if it is detected to exist.
            if (isset($filename) && file_exists(DOWNLOAD_PATH . '/' . $filename)) {
                logger()->warning('Deleting download file: ' . $filename);
                unlink(DOWNLOAD_PATH . '/' . $filename);
            }
            throw new DownloaderException('Download failed! ' . $e->getMessage());
        }
    }

    /**
     * Use curl command to get http response
     *
     * @throws DownloaderException
     */
    public static function curlExec(string $url, string $method = 'GET', array $headers = [], array $hooks = [], int $retry = 0): string
    {
        foreach ($hooks as $hook) {
            $hook($method, $url, $headers);
        }

        try {
            FileSystem::findCommandPath('curl');

            $methodArg = match ($method) {
                'GET' => '',
                'HEAD' => '-I',
                default => "-X \"{$method}\"",
            };
            $headerArg = implode(' ', array_map(fn ($v) => '"-H' . $v . '"', $headers));

            $cmd = SPC_CURL_EXEC . " -sfSL {$methodArg} {$headerArg} \"{$url}\"";
            if (getenv('CACHE_API_EXEC') === 'yes') {
                if (!file_exists(FileSystem::convertPath(DOWNLOAD_PATH . '/.curl_exec_cache'))) {
                    $cache = [];
                } else {
                    $cache = json_decode(file_get_contents(FileSystem::convertPath(DOWNLOAD_PATH . '/.curl_exec_cache')), true);
                }
                if (isset($cache[$cmd]) && $cache[$cmd]['expire'] >= time()) {
                    return $cache[$cmd]['cache'];
                }
                f_exec($cmd, $output, $ret);
                if ($ret === 2 || $ret === -1073741510) {
                    throw new RuntimeException('failed http fetch');
                }
                if ($ret !== 0) {
                    throw new DownloaderException('failed http fetch');
                }
                $cache[$cmd]['cache'] = implode("\n", $output);
                $cache[$cmd]['expire'] = time() + 3600;
                file_put_contents(FileSystem::convertPath(DOWNLOAD_PATH . '/.curl_exec_cache'), json_encode($cache));
                return $cache[$cmd]['cache'];
            }
            f_exec($cmd, $output, $ret);
            if ($ret === 2 || $ret === -1073741510) {
                throw new RuntimeException('failed http fetch');
            }
            if ($ret !== 0) {
                throw new DownloaderException('failed http fetch');
            }
            return implode("\n", $output);
        } catch (DownloaderException $e) {
            if ($retry > 0) {
                logger()->notice('Retrying curl exec ...');
                return self::curlExec($url, $method, $headers, $hooks, $retry - 1);
            }
            throw $e;
        }
    }

    /**
     * Use curl to download sources from url
     *
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public static function curlDown(string $url, string $path, string $method = 'GET', array $headers = [], array $hooks = [], int $retry = 0): void
    {
        $used_headers = $headers;
        foreach ($hooks as $hook) {
            $hook($method, $url, $used_headers);
        }

        $methodArg = match ($method) {
            'GET' => '',
            'HEAD' => '-I',
            default => "-X \"{$method}\"",
        };
        $headerArg = implode(' ', array_map(fn ($v) => '"-H' . $v . '"', $used_headers));
        $check = !defined('DEBUG_MODE') ? 's' : '#';
        $cmd = SPC_CURL_EXEC . " -{$check}fSL -o \"{$path}\" {$methodArg} {$headerArg} \"{$url}\"";
        try {
            f_passthru($cmd);
        } catch (RuntimeException $e) {
            if ($e->getCode() === 2 || $e->getCode() === -1073741510) {
                throw new WrongUsageException('Keyboard interrupted, download failed !');
            }
            if ($retry > 0) {
                logger()->notice('Retrying curl download ...');
                self::curlDown($url, $path, $method, $used_headers, retry: $retry - 1);
                return;
            }
            throw $e;
        }
    }

    public static function getPreBuiltLockName(string $source): string
    {
        return "{$source}-" . PHP_OS_FAMILY . '-' . getenv('GNU_ARCH') . '-' . (getenv('SPC_LIBC') ?: 'default') . '-' . (SystemUtil::getLibcVersionIfExists() ?? 'default');
    }

    /**
     * Register CTRL+C event for different OS.
     *
     * @param callable $callback callback function
     */
    private static function registerCancelEvent(callable $callback): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            sapi_windows_set_ctrl_handler($callback);
        } elseif (extension_loaded('pcntl')) {
            pcntl_signal(2, $callback);
        } else {
            logger()->debug('You have not enabled `pcntl` extension, cannot prevent download file corruption when Ctrl+C');
        }
    }

    /**
     * Unegister CTRL+C event for different OS.
     */
    private static function unregisterCancelEvent(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            sapi_windows_set_ctrl_handler(null);
        } elseif (extension_loaded('pcntl')) {
            pcntl_signal(2, SIG_IGN);
        }
    }

    private static function getRetryTime(): int
    {
        return intval(getenv('SPC_RETRY_TIME') ? getenv('SPC_RETRY_TIME') : 0);
    }

    /**
     * @throws FileSystemException
     */
    private static function isAlreadyDownloaded(string $name, bool $force, int $download_as = SPC_DOWNLOAD_SOURCE): bool
    {
        if (!file_exists(DOWNLOAD_PATH . '/.lock.json')) {
            $lock = [];
        } else {
            $lock = json_decode(FileSystem::readFile(DOWNLOAD_PATH . '/.lock.json'), true) ?? [];
        }
        // If lock file exists, skip downloading for source mode
        if (!$force && $download_as === SPC_DOWNLOAD_SOURCE && isset($lock[$name])) {
            if (
                $lock[$name]['source_type'] === 'archive' && file_exists(DOWNLOAD_PATH . '/' . $lock[$name]['filename']) ||
                $lock[$name]['source_type'] === 'dir' && is_dir(DOWNLOAD_PATH . '/' . $lock[$name]['dirname'])
            ) {
                logger()->notice("Source [{$name}] already downloaded: " . ($lock[$name]['filename'] ?? $lock[$name]['dirname']));
                return true;
            }
        }
        // If lock file exists for current arch and glibc target, skip downloading

        if (!$force && $download_as === SPC_DOWNLOAD_PRE_BUILT && isset($lock[$lock_name = self::getPreBuiltLockName($name)])) {
            // lock name with env
            if (
                $lock[$lock_name]['source_type'] === 'archive' && file_exists(DOWNLOAD_PATH . '/' . $lock[$lock_name]['filename']) ||
                $lock[$lock_name]['source_type'] === 'dir' && is_dir(DOWNLOAD_PATH . '/' . $lock[$lock_name]['dirname'])
            ) {
                logger()->notice("Pre-built content [{$name}] already downloaded: " . ($lock[$lock_name]['filename'] ?? $lock[$lock_name]['dirname']));
                return true;
            }
        }
        return false;
    }
}
