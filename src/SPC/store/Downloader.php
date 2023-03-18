<?php

declare(strict_types=1);

namespace SPC\store;

use JetBrains\PhpStorm\ArrayShape;
use SPC\exception\DownloaderException;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

/**
 * 资源下载器
 */
class Downloader
{
    /**
     * 获取 BitBucket 仓库的最新 Tag
     *
     * @param  string              $name   资源名称
     * @param  array               $source 资源的元信息，包含字段 repo
     * @return array<int, string>  返回下载 url 链接和文件名
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
     * 获取 GitHub 最新的打包地址和文件名
     *
     * @param  string              $name   包名称
     * @param  array               $source 源信息
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
     * 获取 GitHub 最新的 Release 下载信息
     *
     * @param  string              $name   资源名
     * @param  array               $source 资源的元信息，包含字段 repo、match
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
        foreach ($data[0]['assets'] as $asset) {
            if (preg_match('|' . $source['match'] . '|', $asset['name'])) {
                $url = $asset['browser_download_url'];
                break;
            }
        }
        if (!$url) {
            throw new DownloaderException("failed to find {$name} source");
        }
        $filename = basename($url);

        return [$url, $filename];
    }

    /**
     * 获取文件列表的资源链接和名称
     *
     * @param  string              $name   资源名称
     * @param  array               $source 资源元信息，包含 url、regex
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

        return [$source['url'] . end($versions), end($versions)];
    }

    /**
     * 通过链接下载资源到本地并解压
     *
     * @param  string              $name     资源名称
     * @param  string              $url      下载链接
     * @param  string              $filename 下载到下载目录的目标文件名称，例如 xz.tar.gz
     * @param  null|string         $path     如果指定了此参数，则会移动该资源目录到目标目录
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws DownloaderException
     */
    public static function downloadUrl(string $name, string $url, string $filename, ?string $path = null): void
    {
        if (!file_exists(DOWNLOAD_PATH . "/{$filename}")) {
            logger()->debug("downloading {$url}");
            self::curlDown(url: $url, path: DOWNLOAD_PATH . "/{$filename}");
        } else {
            logger()->notice("{$filename} already exists");
        }
        FileSystem::extractSource($name, DOWNLOAD_PATH . "/{$filename}");
        if ($path) {
            $path = FileSystem::convertPath(SOURCE_PATH . "/{$path}");
            $src_path = FileSystem::convertPath(SOURCE_PATH . "/{$name}");
            switch (PHP_OS_FAMILY) {
                case 'Windows':
                    f_passthru('move "' . $src_path . '" "' . $path . '"');
                    break;
                case 'Linux':
                case 'Darwin':
                    f_passthru('mv "' . $src_path . '" "' . $path . '"');
                    break;
            }
        }
    }

    public static function downloadGit(string $name, string $url, string $branch, ?string $path = null): void
    {
        if ($path) {
            $path = SOURCE_PATH . "/{$path}";
        } else {
            $path = DOWNLOAD_PATH . "/{$name}";
        }
        $download_path = DOWNLOAD_PATH . "/{$name}";
        if (file_exists($download_path)) {
            logger()->notice("{$name} git source already fetched");
        } else {
            logger()->debug("cloning {$name} source");
            $check = !defined('DEBUG_MODE') ? ' -q' : '';
            f_passthru(
                'git clone' . $check .
                ' --config core.autocrlf=false ' .
                "--branch \"{$branch}\" " . (defined('GIT_SHALLOW_CLONE') ? '--depth 1 --single-branch' : '') . " --recursive \"{$url}\" \"{$download_path}\""
            );
        }
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
        }
    }

    /**
     * 拉取资源
     *
     * @param  string              $name   资源名称
     * @param  array               $source 资源参数，包含 type、path、rev、url、filename、regex、license
     * @throws DownloaderException
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public static function fetchSource(string $name, array $source): void
    {
        // 避免重复 fetch
        if (is_dir(FileSystem::convertPath(SOURCE_PATH . "/{$name}")) || isset($source['path']) && is_dir(FileSystem::convertPath(SOURCE_PATH . "/{$source['path']}"))) {
            logger()->notice("{$name} source already extracted");
            return;
        }
        try {
            switch ($source['type']) {
                case 'bitbuckettag':    // 从 BitBucket 的 Tag 拉取
                    [$url, $filename] = self::getLatestBitbucketTag($name, $source);
                    self::downloadUrl($name, $url, $filename, $source['path'] ?? null);
                    break;
                case 'ghtar':           // 从 GitHub 的 TarBall 拉取
                    [$url, $filename] = self::getLatestGithubTarball($name, $source);
                    self::downloadUrl($name, $url, $filename, $source['path'] ?? null);
                    break;
                case 'ghtagtar':        // 根据 GitHub 的 Tag 拉取相应版本的 Tar
                    [$url, $filename] = self::getLatestGithubTarball($name, $source, 'tags');
                    self::downloadUrl($name, $url, $filename, $source['path'] ?? null);
                    break;
                case 'ghrel':           // 通过 GitHub Release 来拉取
                    [$url, $filename] = self::getLatestGithubRelease($name, $source);
                    self::downloadUrl($name, $url, $filename, $source['path'] ?? null);
                    break;
                case 'filelist':        // 通过网站提供的 filelist 使用正则提取后拉取
                    [$url, $filename] = self::getFromFileList($name, $source);
                    self::downloadUrl($name, $url, $filename, $source['path'] ?? null);
                    break;
                case 'url':             // 通过直链拉取
                    $url = $source['url'];
                    $filename = $source['filename'] ?? basename($source['url']);
                    self::downloadUrl($name, $url, $filename, $source['path'] ?? null);
                    break;
                case 'git':             // 通过拉回 Git 仓库的形式拉取
                    self::downloadGit($name, $source['url'], $source['rev'], $source['path'] ?? null);
                    break;
                default:
                    throw new DownloaderException('unknown source type: ' . $source['type']);
            }
        } catch (RuntimeException $e) {
            // 因为某些时候通过命令行下载的文件在失败后不会删除，这里检测到文件存在需要手动删一下
            if (isset($filename) && file_exists(DOWNLOAD_PATH . '/' . $filename)) {
                logger()->warning('Deleting download file: ' . $filename);
                unlink(DOWNLOAD_PATH . '/' . $filename);
            }
            throw $e;
        }
    }

    /**
     * 获取 PHP x.y 的具体版本号，例如通过 8.1 来获取 8.1.10
     *
     * @throws DownloaderException
     */
    #[ArrayShape(['type' => 'string', 'path' => 'string', 'rev' => 'string', 'url' => 'string'])]
    public static function getLatestPHPInfo(string $major_version): array
    {
        // 查找最新的小版本号
        $info = json_decode(self::curlExec(url: "https://www.php.net/releases/index.php?json&version={$major_version}"), true);
        $version = $info['version'];

        // 从官网直接下载
        return [
            'type' => 'url',
            'url' => "https://www.php.net/distributions/php-{$version}.tar.gz",
        ];
    }

    /**
     * 使用 curl 命令拉取元信息
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

        $cmd = "curl -sfSL {$methodArg} {$headerArg} \"{$url}\"";
        f_exec($cmd, $output, $ret);
        if ($ret !== 0) {
            throw new DownloaderException('failed http fetch');
        }
        return implode("\n", $output);
    }

    /**
     * 使用 curl 命令下载文件
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
        $cmd = "curl -{$check}fSL -o \"{$path}\" {$methodArg} {$headerArg} \"{$url}\"";
        f_passthru($cmd);
    }
}
