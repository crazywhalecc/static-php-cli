<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\exception\DownloaderException;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\pkg\CustomPackage;
use SPC\store\source\CustomSourceBase;
use SPC\util\SPCTarget;

/**
 * Source Downloader.
 */
class Downloader
{
    /**
     * Get latest version from BitBucket tag
     *
     * @param  string              $name   Source name
     * @param  array               $source Source meta info: [repo]
     * @return array<int, string>  [url, filename]
     * @throws DownloaderException
     * @throws RuntimeException
     */
    public static function getLatestBitbucketTag(string $name, array $source): array
    {
        logger()->debug("finding {$name} source from bitbucket tag");
        $data = json_decode(self::curlExec(
            url: "https://api.bitbucket.org/2.0/repositories/{$source['repo']}/refs/tags",
            retries: self::getRetryAttempts()
        ), true);
        $ver = $data['values'][0]['name'];
        if (!$ver) {
            throw new DownloaderException("failed to find {$name} bitbucket source");
        }
        $url = "https://bitbucket.org/{$source['repo']}/get/{$ver}.tar.gz";
        $headers = self::curlExec(
            url: $url,
            method: 'HEAD',
            retries: self::getRetryAttempts()
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
     * @param  string              $name   Source name
     * @param  array               $source Source meta info: [repo]
     * @param  string              $type   Type of tarball, default is 'releases'
     * @return array<int, string>  [url, filename]
     * @throws DownloaderException
     */
    public static function getLatestGithubTarball(string $name, array $source, string $type = 'releases'): array
    {
        logger()->debug("finding {$name} source from github {$type} tarball");
        $data = json_decode(self::curlExec(
            url: "https://api.github.com/repos/{$source['repo']}/{$type}",
            hooks: [[CurlHook::class, 'setupGithubToken']],
            retries: self::getRetryAttempts()
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
            retries: self::getRetryAttempts()
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
     * @param  string              $name         Source name
     * @param  array               $source       Source meta info: [repo, match]
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
            retries: self::getRetryAttempts()
        ), true);
        $url = null;
        $filename = null;
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
                    $url = "https://api.github.com/repos/{$source['repo']}/releases/assets/{$asset['id']}";
                    $filename = $asset['name'];
                    break 2;
                }
            }
        }

        if (!$url || !$filename) {
            throw new DownloaderException("failed to find {$name} release metadata");
        }

        return [$url, $filename];
    }

    /**
     * Get latest version from file list (regex based crawler)
     *
     * @param  string              $name   Source name
     * @param  array               $source Source meta info: [filelist]
     * @return array<int, string>  [url, filename]
     * @throws DownloaderException
     */
    public static function getFromFileList(string $name, array $source): array
    {
        logger()->debug("finding {$name} source from file list");
        $page = self::curlExec($source['url'], retries: self::getRetryAttempts());
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
     * Download file from URL
     *
     * @param  string              $name        Download name
     * @param  string              $url         Download URL
     * @param  string              $filename    Target filename
     * @param  null|string         $move_path   Optional move path after download
     * @param  int                 $download_as Download type constant
     * @param  array               $headers     Optional HTTP headers
     * @param  array               $hooks       Optional curl hooks
     * @throws DownloaderException
     * @throws RuntimeException
     */
    public static function downloadFile(string $name, string $url, string $filename, ?string $move_path = null, int $download_as = SPC_DOWNLOAD_SOURCE, array $headers = [], array $hooks = []): void
    {
        logger()->debug("Downloading {$url}");
        $cancel_func = function () use ($filename) {
            if (file_exists(FileSystem::convertPath(DOWNLOAD_PATH . '/' . $filename))) {
                logger()->warning('Deleting download file: ' . $filename);
                unlink(FileSystem::convertPath(DOWNLOAD_PATH . '/' . $filename));
            }
        };
        self::registerCancelEvent($cancel_func);
        self::curlDown(url: $url, path: FileSystem::convertPath(DOWNLOAD_PATH . "/{$filename}"), headers: $headers, hooks: $hooks, retries: self::getRetryAttempts());
        self::unregisterCancelEvent();
        logger()->debug("Locking {$filename}");
        if ($download_as === SPC_DOWNLOAD_PRE_BUILT) {
            $name = self::getPreBuiltLockName($name);
        }
        LockFile::lockSource($name, ['source_type' => SPC_SOURCE_ARCHIVE, 'filename' => $filename, 'move_path' => $move_path, 'lock_as' => $download_as]);
    }

    /**
     * Download Git repository
     *
     * @param  string              $name       Repository name
     * @param  string              $url        Git repository URL
     * @param  string              $branch     Branch to checkout
     * @param  null|array          $submodules Optional submodules to initialize
     * @param  null|string         $move_path  Optional move path after download
     * @param  int                 $retries    Number of retry attempts
     * @param  int                 $lock_as    Lock type constant
     * @throws DownloaderException
     * @throws RuntimeException
     */
    public static function downloadGit(string $name, string $url, string $branch, ?array $submodules = null, ?string $move_path = null, int $retries = 0, int $lock_as = SPC_DOWNLOAD_SOURCE): void
    {
        $download_path = FileSystem::convertPath(DOWNLOAD_PATH . "/{$name}");
        if (file_exists($download_path)) {
            FileSystem::removeDir($download_path);
        }
        logger()->debug("cloning {$name} source");

        $quiet = !defined('DEBUG_MODE') ? '-q --quiet' : '';
        $git = SPC_GIT_EXEC;
        $shallow = defined('GIT_SHALLOW_CLONE') ? '--depth 1 --single-branch' : '';
        $recursive = ($submodules === null) ? '--recursive' : '';

        try {
            self::registerCancelEvent(function () use ($download_path) {
                if (is_dir($download_path)) {
                    logger()->warning('Removing path ' . $download_path);
                    FileSystem::removeDir($download_path);
                }
            });
            f_passthru("{$git} clone {$quiet} --config core.autocrlf=false --branch \"{$branch}\" {$shallow} {$recursive} \"{$url}\" \"{$download_path}\"");
            if ($submodules !== null) {
                foreach ($submodules as $submodule) {
                    f_passthru("cd \"{$download_path}\" && {$git} submodule update --init " . escapeshellarg($submodule));
                }
            }
        } catch (RuntimeException $e) {
            if (is_dir($download_path)) {
                FileSystem::removeDir($download_path);
            }
            if ($e->getCode() === 2 || $e->getCode() === -1073741510) {
                throw new WrongUsageException('Keyboard interrupted, download failed !');
            }
            if ($retries > 0) {
                self::downloadGit($name, $url, $branch, $submodules, $move_path, $retries - 1, $lock_as);
                return;
            }
            throw $e;
        } finally {
            self::unregisterCancelEvent();
        }
        // Lock
        logger()->debug("Locking git source {$name}");
        LockFile::lockSource($name, ['source_type' => SPC_SOURCE_GIT, 'dirname' => $name, 'move_path' => $move_path, 'lock_as' => $lock_as]);

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
                    self::downloadFile($name, $url, $filename, $pkg['extract'] ?? null, SPC_DOWNLOAD_PACKAGE, hooks: [[CurlHook::class, 'setupGithubToken']]);
                    break;
                case 'ghtagtar':        // GitHub Tag (tar)
                    [$url, $filename] = self::getLatestGithubTarball($name, $pkg, 'tags');
                    self::downloadFile($name, $url, $filename, $pkg['extract'] ?? null, SPC_DOWNLOAD_PACKAGE, hooks: [[CurlHook::class, 'setupGithubToken']]);
                    break;
                case 'ghrel':           // GitHub Release (uploaded)
                    [$url, $filename] = self::getLatestGithubRelease($name, $pkg);
                    self::downloadFile($name, $url, $filename, $pkg['extract'] ?? null, SPC_DOWNLOAD_PACKAGE, ['Accept: application/octet-stream'], [[CurlHook::class, 'setupGithubToken']]);
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
                        $pkg['submodules'] ?? null,
                        $pkg['extract'] ?? null,
                        self::getRetryAttempts(),
                        SPC_DOWNLOAD_PRE_BUILT
                    );
                    break;
                case 'local':
                    // Local directory, do nothing, just lock it
                    logger()->debug("Locking local source {$name}");
                    LockFile::lockSource($name, [
                        'source_type' => SPC_SOURCE_LOCAL,
                        'dirname' => $pkg['dirname'],
                        'move_path' => $pkg['extract'] ?? null,
                        'lock_as' => SPC_DOWNLOAD_PACKAGE,
                    ]);
                    break;
                case 'custom':          // Custom download method, like API-based download or other
                    $classes = FileSystem::getClassesPsr4(ROOT_DIR . '/src/SPC/store/pkg', 'SPC\store\pkg');
                    if (isset($pkg['func']) && is_callable($pkg['func'])) {
                        $pkg['name'] = $name;
                        $pkg['func']($force, $pkg, SPC_DOWNLOAD_PACKAGE);
                        break;
                    }
                    foreach ($classes as $class) {
                        if (is_a($class, CustomPackage::class, true) && $class !== CustomPackage::class) {
                            $cls = new $class();
                            if (in_array($name, $cls->getSupportName())) {
                                (new $class())->fetch($name, $force, $pkg);
                            }
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
     * Download source
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
                    self::downloadFile($name, $url, $filename, $source['path'] ?? null, $download_as, hooks: [[CurlHook::class, 'setupGithubToken']]);
                    break;
                case 'ghtagtar':        // GitHub Tag (tar)
                    [$url, $filename] = self::getLatestGithubTarball($name, $source, 'tags');
                    self::downloadFile($name, $url, $filename, $source['path'] ?? null, $download_as, hooks: [[CurlHook::class, 'setupGithubToken']]);
                    break;
                case 'ghrel':           // GitHub Release (uploaded)
                    [$url, $filename] = self::getLatestGithubRelease($name, $source);
                    self::downloadFile($name, $url, $filename, $source['path'] ?? null, $download_as, ['Accept: application/octet-stream'], [[CurlHook::class, 'setupGithubToken']]);
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
                        $source['submodules'] ?? null,
                        $source['path'] ?? null,
                        self::getRetryAttempts(),
                        $download_as
                    );
                    break;
                case 'local':
                    // Local directory, do nothing, just lock it
                    logger()->debug("Locking local source {$name}");
                    LockFile::lockSource($name, [
                        'source_type' => SPC_SOURCE_LOCAL,
                        'dirname' => $source['dirname'],
                        'move_path' => $source['extract'] ?? null,
                        'lock_as' => $download_as,
                    ]);
                    break;
                case 'custom':          // Custom download method, like API-based download or other
                    if (isset($source['func']) && is_callable($source['func'])) {
                        $source['name'] = $name;
                        $source['func']($force, $source, $download_as);
                        break;
                    }
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
     * @param  string              $url     Target URL
     * @param  string              $method  HTTP method (GET, POST, etc.)
     * @param  array               $headers HTTP headers
     * @param  array               $hooks   Curl hooks
     * @param  int                 $retries Number of retry attempts
     * @return string              Response body
     * @throws DownloaderException
     * @throws RuntimeException
     */
    public static function curlExec(string $url, string $method = 'GET', array $headers = [], array $hooks = [], int $retries = 0): string
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
        $retry = $retries > 0 ? "--retry {$retries}" : '';
        $cmd = SPC_CURL_EXEC . " -sfSL {$retry} {$methodArg} {$headerArg} \"{$url}\"";
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
                throw new RuntimeException(sprintf('Failed to fetch "%s"', $url));
            }
            if ($ret !== 0) {
                throw new DownloaderException(sprintf('Failed to fetch "%s"', $url));
            }
            $cache[$cmd]['cache'] = implode("\n", $output);
            $cache[$cmd]['expire'] = time() + 3600;
            file_put_contents(FileSystem::convertPath(DOWNLOAD_PATH . '/.curl_exec_cache'), json_encode($cache));
            return $cache[$cmd]['cache'];
        }
        f_exec($cmd, $output, $ret);
        if ($ret === 2 || $ret === -1073741510) {
            throw new RuntimeException(sprintf('Failed to fetch "%s"', $url));
        }
        if ($ret !== 0) {
            throw new DownloaderException(sprintf('Failed to fetch "%s"', $url));
        }
        return implode("\n", $output);
    }

    /**
     * Use curl to download sources from url
     *
     * @param  string              $url     Download URL
     * @param  string              $path    Target file path
     * @param  string              $method  HTTP method
     * @param  array               $headers HTTP headers
     * @param  array               $hooks   Curl hooks
     * @param  int                 $retries Number of retry attempts
     * @throws DownloaderException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public static function curlDown(string $url, string $path, string $method = 'GET', array $headers = [], array $hooks = [], int $retries = 0): void
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
        $retry = $retries > 0 ? "--retry {$retries}" : '';
        $cmd = SPC_CURL_EXEC . " -{$check}fSL {$retry} -o \"{$path}\" {$methodArg} {$headerArg} \"{$url}\"";
        try {
            f_passthru($cmd);
        } catch (RuntimeException $e) {
            if ($e->getCode() === 2 || $e->getCode() === -1073741510) {
                throw new WrongUsageException('Keyboard interrupted, download failed !');
            }
            throw $e;
        }
    }

    /**
     * Get pre-built lock name from source
     *
     * @param  string $source Source name
     * @return string Lock name
     */
    public static function getPreBuiltLockName(string $source): string
    {
        $os_family = PHP_OS_FAMILY;
        $gnu_arch = getenv('GNU_ARCH') ?: 'unknown';
        $libc = SPCTarget::getLibc();
        $libc_version = SPCTarget::getLibcVersion() ?? 'default';

        return "{$source}-{$os_family}-{$gnu_arch}-{$libc}-{$libc_version}";
    }

    /**
     * Get default alternative source
     *
     * @param  string $source_name Source name
     * @return array  Alternative source configuration
     */
    public static function getDefaultAlternativeSource(string $source_name): array
    {
        return [
            'type' => 'custom',
            'func' => function (bool $force, array $source, int $download_as) use ($source_name) {
                logger()->debug("Fetching alternative source for {$source_name}");
                // get from dl.static-php.dev
                $url = "https://dl.static-php.dev/static-php-cli/deps/spc-download-mirror/{$source_name}/?format=json";
                $json = json_decode(Downloader::curlExec(url: $url, retries: intval(getenv('SPC_DOWNLOAD_RETRIES') ?: 0)), true);
                if (!is_array($json)) {
                    throw new RuntimeException('failed http fetch');
                }
                $item = $json[0] ?? null;
                if ($item === null) {
                    throw new RuntimeException('failed to parse json');
                }
                $full_url = 'https://dl.static-php.dev' . $item['full_path'];
                $filename = basename($item['full_path']);
                Downloader::downloadFile($source_name, $full_url, $filename, $source['path'] ?? null, $download_as);
            },
        ];
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

    private static function getRetryAttempts(): int
    {
        return intval(getenv('SPC_DOWNLOAD_RETRIES') ?: 0);
    }

    /**
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    private static function isAlreadyDownloaded(string $name, bool $force, int $download_as = SPC_DOWNLOAD_SOURCE): bool
    {
        // If the lock file exists, skip downloading for source mode
        $lock_item = LockFile::get($name);
        if (!$force && $download_as === SPC_DOWNLOAD_SOURCE && $lock_item !== null) {
            if (file_exists($path = LockFile::getLockFullPath($lock_item))) {
                logger()->notice("Source [{$name}] already downloaded: {$path}");
                return true;
            }
        }
        $lock_name = self::getPreBuiltLockName($name);
        $lock_item = LockFile::get($lock_name);
        if (!$force && $download_as === SPC_DOWNLOAD_PRE_BUILT && $lock_item !== null) {
            // lock name with env
            if (file_exists($path = LockFile::getLockFullPath($lock_item))) {
                logger()->notice("Pre-built content [{$name}] already downloaded: {$path}");
                return true;
            }
        }
        if (!$force && $download_as === SPC_DOWNLOAD_PACKAGE && $lock_item !== null) {
            if (file_exists($path = LockFile::getLockFullPath($lock_item))) {
                logger()->notice("Source [{$name}] already downloaded: {$path}");
                return true;
            }
        }
        return false;
    }
}
