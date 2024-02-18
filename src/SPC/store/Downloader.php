<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\exception\DownloaderException;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\source\CustomSourceBase;

/**
 * Source Downloader.
 */
class Downloader
{
    /**
     * Get latest version from BitBucket tag
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
            url: "https://api.bitbucket.org/2.0/repositories/{$source['repo']}/refs/tags"
        ), true);
        $ver = $data['values'][0]['name'];
        if (!$ver) {
            throw new DownloaderException("failed to find {$name} bitbucket source");
        }
        $url = "https://bitbucket.org/{$source['repo']}/get/{$ver}.tar.gz";
        $headers = self::curlExec(
            url: $url,
            method: 'HEAD'
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
     * Get latest version from GitHub tarball
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
            hooks: [[CurlHook::class, 'setupGithubToken']]
        ), true);
        $url = $data[0]['tarball_url'];
        if (!$url) {
            throw new DownloaderException("failed to find {$name} source");
        }
        $headers = self::curlExec(
            url: $url,
            method: 'HEAD',
            hooks: [[CurlHook::class, 'setupGithubToken']],
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
     * @param  string              $name   source name
     * @param  array               $source source meta info: [repo, match]
     * @return array<int, string>  [url, filename]
     * @throws DownloaderException
     */
    public static function getLatestGithubRelease(string $name, array $source): array
    {
        logger()->debug("finding {$name} source from github releases assests");
        $data = json_decode(self::curlExec(
            url: "https://api.github.com/repos/{$source['repo']}/releases",
            hooks: [[CurlHook::class, 'setupGithubToken']],
        ), true);
        $url = null;
        foreach ($data as $release) {
            if ($release['prerelease'] === true) {
                continue;
            }
            foreach ($release['assets'] as $asset) {
                if (preg_match('|' . $source['match'] . '|', $asset['name'])) {
                    $url = $asset['browser_download_url'];
                    break 2;
                }
            }
        }

        if (!$url) {
            throw new DownloaderException("failed to find {$name} source");
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
        $page = self::curlExec($source['url']);
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
     * @throws DownloaderException
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public static function downloadFile(string $name, string $url, string $filename, ?string $move_path = null): void
    {
        logger()->debug("Downloading {$url}");
        $cancel_func = function () use ($filename) {
            if (file_exists(FileSystem::convertPath(DOWNLOAD_PATH . '/' . $filename))) {
                logger()->warning('Deleting download file: ' . $filename);
                unlink(FileSystem::convertPath(DOWNLOAD_PATH . '/' . $filename));
            }
        };
        self::registerCancelEvent($cancel_func);
        self::curlDown(url: $url, path: FileSystem::convertPath(DOWNLOAD_PATH . "/{$filename}"));
        self::unregisterCancelEvent();
        logger()->debug("Locking {$filename}");
        self::lockSource($name, ['source_type' => 'archive', 'filename' => $filename, 'move_path' => $move_path]);
    }

    /**
     * Try to lock source.
     *
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
     */
    public static function downloadGit(string $name, string $url, string $branch, ?string $move_path = null): void
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
        self::registerCancelEvent($cancel_func);
        f_passthru(
            SPC_GIT_EXEC . ' clone' . $check .
            ' --config core.autocrlf=false ' .
            "--branch \"{$branch}\" " . (defined('GIT_SHALLOW_CLONE') ? '--depth 1 --single-branch' : '') . " --recursive \"{$url}\" \"{$download_path}\""
        );
        self::unregisterCancelEvent();

        // Lock
        logger()->debug("Locking git source {$name}");
        self::lockSource($name, ['source_type' => 'dir', 'dirname' => $name, 'move_path' => $move_path]);

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

        // load lock file
        if (!file_exists(DOWNLOAD_PATH . '/.lock.json')) {
            $lock = [];
        } else {
            $lock = json_decode(FileSystem::readFile(DOWNLOAD_PATH . '/.lock.json'), true) ?? [];
        }
        // If lock file exists, skip downloading
        if (isset($lock[$name]) && !$force) {
            if ($lock[$name]['source_type'] === 'archive' && file_exists(DOWNLOAD_PATH . '/' . $lock[$name]['filename'])) {
                logger()->notice("Package [{$name}] already downloaded: " . $lock[$name]['filename']);
                return;
            }
            if ($lock[$name]['source_type'] === 'dir' && is_dir(DOWNLOAD_PATH . '/' . $lock[$name]['dirname'])) {
                logger()->notice("Package [{$name}] already downloaded: " . $lock[$name]['dirname']);
                return;
            }
        }

        try {
            switch ($pkg['type']) {
                case 'bitbuckettag':    // BitBucket Tag
                    [$url, $filename] = self::getLatestBitbucketTag($name, $pkg);
                    self::downloadFile($name, $url, $filename, $pkg['extract'] ?? null);
                    break;
                case 'ghtar':           // GitHub Release (tar)
                    [$url, $filename] = self::getLatestGithubTarball($name, $pkg);
                    self::downloadFile($name, $url, $filename, $pkg['extract'] ?? null);
                    break;
                case 'ghtagtar':        // GitHub Tag (tar)
                    [$url, $filename] = self::getLatestGithubTarball($name, $pkg, 'tags');
                    self::downloadFile($name, $url, $filename, $pkg['extract'] ?? null);
                    break;
                case 'ghrel':           // GitHub Release (uploaded)
                    [$url, $filename] = self::getLatestGithubRelease($name, $pkg);
                    self::downloadFile($name, $url, $filename, $pkg['extract'] ?? null);
                    break;
                case 'filelist':        // Basic File List (regex based crawler)
                    [$url, $filename] = self::getFromFileList($name, $pkg);
                    self::downloadFile($name, $url, $filename, $pkg['extract'] ?? null);
                    break;
                case 'url':             // Direct download URL
                    $url = $pkg['url'];
                    $filename = $pkg['filename'] ?? basename($pkg['url']);
                    self::downloadFile($name, $url, $filename, $pkg['extract'] ?? null);
                    break;
                case 'git':             // Git repo
                    self::downloadGit($name, $pkg['url'], $pkg['rev'], $pkg['extract'] ?? null);
                    break;
                case 'custom':          // Custom download method, like API-based download or other
                    $classes = FileSystem::getClassesPsr4(ROOT_DIR . '/src/SPC/store/source', 'SPC\\store\\source');
                    foreach ($classes as $class) {
                        if (is_a($class, CustomSourceBase::class, true) && $class::NAME === $name) {
                            (new $class())->fetch();
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
     * @param  string              $name   source name
     * @param  null|array          $source source meta info: [type, path, rev, url, filename, regex, license]
     * @throws DownloaderException
     * @throws FileSystemException
     */
    public static function downloadSource(string $name, ?array $source = null, bool $force = false): void
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
        if (!file_exists(DOWNLOAD_PATH . '/.lock.json')) {
            $lock = [];
        } else {
            $lock = json_decode(FileSystem::readFile(DOWNLOAD_PATH . '/.lock.json'), true) ?? [];
        }
        // If lock file exists, skip downloading
        if (isset($lock[$name]) && !$force) {
            if ($lock[$name]['source_type'] === 'archive' && file_exists(DOWNLOAD_PATH . '/' . $lock[$name]['filename'])) {
                logger()->notice("source [{$name}] already downloaded: " . $lock[$name]['filename']);
                return;
            }
            if ($lock[$name]['source_type'] === 'dir' && is_dir(DOWNLOAD_PATH . '/' . $lock[$name]['dirname'])) {
                logger()->notice("source [{$name}] already downloaded: " . $lock[$name]['dirname']);
                return;
            }
        }

        try {
            switch ($source['type']) {
                case 'bitbuckettag':    // BitBucket Tag
                    [$url, $filename] = self::getLatestBitbucketTag($name, $source);
                    self::downloadFile($name, $url, $filename, $source['path'] ?? null);
                    break;
                case 'ghtar':           // GitHub Release (tar)
                    [$url, $filename] = self::getLatestGithubTarball($name, $source);
                    self::downloadFile($name, $url, $filename, $source['path'] ?? null);
                    break;
                case 'ghtagtar':        // GitHub Tag (tar)
                    [$url, $filename] = self::getLatestGithubTarball($name, $source, 'tags');
                    self::downloadFile($name, $url, $filename, $source['path'] ?? null);
                    break;
                case 'ghrel':           // GitHub Release (uploaded)
                    [$url, $filename] = self::getLatestGithubRelease($name, $source);
                    self::downloadFile($name, $url, $filename, $source['path'] ?? null);
                    break;
                case 'filelist':        // Basic File List (regex based crawler)
                    [$url, $filename] = self::getFromFileList($name, $source);
                    self::downloadFile($name, $url, $filename, $source['path'] ?? null);
                    break;
                case 'url':             // Direct download URL
                    $url = $source['url'];
                    $filename = $source['filename'] ?? basename($source['url']);
                    self::downloadFile($name, $url, $filename, $source['path'] ?? null);
                    break;
                case 'git':             // Git repo
                    self::downloadGit($name, $source['url'], $source['rev'], $source['path'] ?? null);
                    break;
                case 'custom':          // Custom download method, like API-based download or other
                    $classes = FileSystem::getClassesPsr4(ROOT_DIR . '/src/SPC/store/source', 'SPC\\store\\source');
                    foreach ($classes as $class) {
                        if (is_a($class, CustomSourceBase::class, true) && $class::NAME === $name) {
                            (new $class())->fetch();
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
    public static function curlExec(string $url, string $method = 'GET', array $headers = [], array $hooks = []): string
    {
        foreach ($hooks as $hook) {
            $hook($method, $url, $headers);
        }

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
            if ($ret !== 0) {
                throw new DownloaderException('failed http fetch');
            }
            $cache[$cmd]['cache'] = implode("\n", $output);
            $cache[$cmd]['expire'] = time() + 3600;
            file_put_contents(FileSystem::convertPath(DOWNLOAD_PATH . '/.curl_exec_cache'), json_encode($cache));
            return $cache[$cmd]['cache'];
        }
        f_exec($cmd, $output, $ret);
        if ($ret !== 0) {
            throw new DownloaderException('failed http fetch');
        }
        return implode("\n", $output);
    }

    /**
     * Use curl to download sources from url
     *
     * @throws DownloaderException
     * @throws RuntimeException
     */
    public static function curlDown(string $url, string $path, string $method = 'GET', array $headers = [], array $hooks = []): void
    {
        foreach ($hooks as $hook) {
            $hook($method, $url, $headers);
        }

        $methodArg = match ($method) {
            'GET' => '',
            'HEAD' => '-I',
            default => "-X \"{$method}\"",
        };
        $headerArg = implode(' ', array_map(fn ($v) => '"-H' . $v . '"', $headers));
        $check = !defined('DEBUG_MODE') ? 's' : '#';
        $cmd = SPC_CURL_EXEC . " -{$check}fSL -o \"{$path}\" {$methodArg} {$headerArg} \"{$url}\"";
        f_passthru($cmd);
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
            pcntl_signal(SIGINT, $callback);
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
            pcntl_signal(SIGINT, SIG_IGN);
        }
    }
}
