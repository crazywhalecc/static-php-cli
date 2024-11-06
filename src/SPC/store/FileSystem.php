<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

class FileSystem
{
    private static array $_extract_hook = [];

    /**
     * @throws FileSystemException
     */
    public static function loadConfigArray(string $config, ?string $config_dir = null): array
    {
        $whitelist = ['ext', 'lib', 'source', 'pkg', 'pre-built'];
        if (!in_array($config, $whitelist)) {
            throw new FileSystemException('Reading ' . $config . '.json is not allowed');
        }
        $tries = $config_dir !== null ? [FileSystem::convertPath($config_dir . '/' . $config . '.json')] : [
            WORKING_DIR . '/config/' . $config . '.json',
            ROOT_DIR . '/config/' . $config . '.json',
        ];
        foreach ($tries as $try) {
            if (file_exists($try)) {
                $json = json_decode(self::readFile($try), true);
                if (!is_array($json)) {
                    throw new FileSystemException('Reading ' . $try . ' failed');
                }
                return $json;
            }
        }
        throw new FileSystemException('Reading ' . $config . '.json failed');
    }

    /**
     * 读取文件，读不出来直接抛出异常
     *
     * @param  string              $filename 文件路径
     * @throws FileSystemException
     */
    public static function readFile(string $filename): string
    {
        // logger()->debug('Reading file: ' . $filename);
        $r = file_get_contents(self::convertPath($filename));
        if ($r === false) {
            throw new FileSystemException('Reading file ' . $filename . ' failed');
        }
        return $r;
    }

    /**
     * @throws FileSystemException
     */
    public static function replaceFileStr(string $filename, mixed $search = null, mixed $replace = null): false|int
    {
        return self::replaceFile($filename, REPLACE_FILE_STR, $search, $replace);
    }

    /**
     * @throws FileSystemException
     */
    public static function replaceFileRegex(string $filename, mixed $search = null, mixed $replace = null): false|int
    {
        return self::replaceFile($filename, REPLACE_FILE_PREG, $search, $replace);
    }

    /**
     * @throws FileSystemException
     */
    public static function replaceFileUser(string $filename, mixed $callback = null): false|int
    {
        return self::replaceFile($filename, REPLACE_FILE_USER, $callback);
    }

    /**
     * 获取文件后缀
     *
     * @param string $fn 文件名
     */
    public static function extname(string $fn): string
    {
        $parts = explode('.', basename($fn));
        if (count($parts) < 2) {
            return '';
        }
        return array_pop($parts);
    }

    /**
     * 寻找命令的真实路径，效果类似 which
     *
     * @param string $name  命令名称
     * @param array  $paths 路径列表，如果为空则默认从 PATH 系统变量搜索
     */
    public static function findCommandPath(string $name, array $paths = []): ?string
    {
        if (!$paths) {
            $paths = explode(PATH_SEPARATOR, getenv('PATH'));
        }
        if (PHP_OS_FAMILY === 'Windows') {
            foreach ($paths as $path) {
                foreach (['.exe', '.bat', '.cmd'] as $suffix) {
                    if (file_exists($path . DIRECTORY_SEPARATOR . $name . $suffix)) {
                        return $path . DIRECTORY_SEPARATOR . $name . $suffix;
                    }
                }
            }
            return null;
        }
        foreach ($paths as $path) {
            if (file_exists($path . DIRECTORY_SEPARATOR . $name)) {
                return $path . DIRECTORY_SEPARATOR . $name;
            }
        }
        return null;
    }

    /**
     * @throws RuntimeException
     */
    public static function copyDir(string $from, string $to): void
    {
        $dst_path = FileSystem::convertPath($to);
        $src_path = FileSystem::convertPath($from);
        switch (PHP_OS_FAMILY) {
            case 'Windows':
                f_passthru('xcopy "' . $src_path . '" "' . $dst_path . '" /s/e/v/y/i');
                break;
            case 'Linux':
            case 'Darwin':
            case 'BSD':
                f_passthru('cp -r "' . $src_path . '" "' . $dst_path . '"');
                break;
        }
    }

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public static function extractPackage(string $name, string $filename, ?string $extract_path = null): void
    {
        if ($extract_path !== null) {
            // replace
            $extract_path = self::replacePathVariable($extract_path);
            $extract_path = self::isRelativePath($extract_path) ? (WORKING_DIR . '/' . $extract_path) : $extract_path;
        } else {
            $extract_path = PKG_ROOT_PATH . '/' . $name;
        }
        logger()->info("extracting {$name} package to {$extract_path} ...");
        $target = self::convertPath($extract_path);

        if (!is_dir($dir = dirname($target))) {
            self::createDir($dir);
        }
        try {
            self::extractArchive($filename, $target);
        } catch (RuntimeException $e) {
            if (PHP_OS_FAMILY === 'Windows') {
                f_passthru('rmdir /s /q ' . $target);
            } else {
                f_passthru('rm -rf ' . $target);
            }
            throw new FileSystemException('Cannot extract package ' . $name, $e->getCode(), $e);
        }
    }

    /**
     * 解压缩下载的资源包到 source 目录
     *
     * @param  string              $name     资源名
     * @param  string              $filename 文件名
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public static function extractSource(string $name, string $filename, ?string $move_path = null): void
    {
        // if source hook is empty, load it
        if (self::$_extract_hook === []) {
            SourcePatcher::init();
        }
        if ($move_path !== null) {
            $move_path = SOURCE_PATH . '/' . $move_path;
        } else {
            $move_path = SOURCE_PATH . "/{$name}";
        }
        $target = self::convertPath($move_path);
        logger()->info("extracting {$name} source to {$target}" . ' ...');
        if (!is_dir($dir = dirname($target))) {
            self::createDir($dir);
        }
        try {
            self::extractArchive($filename, $target);
            self::emitSourceExtractHook($name, $target);
        } catch (RuntimeException $e) {
            if (PHP_OS_FAMILY === 'Windows') {
                f_passthru('rmdir /s /q ' . $target);
            } else {
                f_passthru('rm -rf ' . $target);
            }
            throw new FileSystemException('Cannot extract source ' . $name . ': ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * 根据系统环境的不同，自动转换路径的分隔符
     *
     * @param string $path 路径
     */
    public static function convertPath(string $path): string
    {
        if (str_starts_with($path, 'phar://')) {
            return $path;
        }
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    public static function convertWinPathToMinGW(string $path): string
    {
        if (preg_match('/^[A-Za-z]:/', $path)) {
            $path = '/' . strtolower(substr($path, 0, 1)) . '/' . str_replace('\\', '/', substr($path, 2));
        }
        return $path;
    }

    /**
     * 递归或非递归扫描目录，可返回相对目录的文件列表或绝对目录的文件列表
     *
     * @param string      $dir         目录
     * @param bool        $recursive   是否递归扫描子目录
     * @param bool|string $relative    是否返回相对目录，如果为true则返回相对目录，如果为false则返回绝对目录
     * @param bool        $include_dir 非递归模式下，是否包含目录
     * @since 2.5
     */
    public static function scanDirFiles(string $dir, bool $recursive = true, bool|string $relative = false, bool $include_dir = false): array|false
    {
        $dir = self::convertPath($dir);
        // 不是目录不扫，直接 false 处理
        if (!file_exists($dir)) {
            logger()->debug('Scan dir failed, no such file or directory.');
            return false;
        }
        if (!is_dir($dir)) {
            logger()->warning('Scan dir failed, not directory.');
            return false;
        }
        logger()->debug('scanning directory ' . $dir);
        // 套上 zm_dir
        $scan_list = scandir($dir);
        if ($scan_list === false) {
            logger()->warning('Scan dir failed, cannot scan directory: ' . $dir);
            return false;
        }
        $list = [];
        // 将 relative 置为相对目录的前缀
        if ($relative === true) {
            $relative = $dir;
        }
        // 遍历目录
        foreach ($scan_list as $v) {
            // Unix 系统排除这俩目录
            if ($v == '.' || $v == '..') {
                continue;
            }
            $sub_file = self::convertPath($dir . '/' . $v);
            if (is_dir($sub_file) && $recursive) {
                # 如果是 目录 且 递推 , 则递推添加下级文件
                $list = array_merge($list, self::scanDirFiles($sub_file, $recursive, $relative));
            } elseif (is_file($sub_file) || is_dir($sub_file) && !$recursive && $include_dir) {
                # 如果是 文件 或 (是 目录 且 不递推 且 包含目录)
                if (is_string($relative) && mb_strpos($sub_file, $relative) === 0) {
                    $list[] = ltrim(mb_substr($sub_file, mb_strlen($relative)), '/\\');
                } elseif ($relative === false) {
                    $list[] = $sub_file;
                }
            }
        }
        return $list;
    }

    /**
     * 获取该路径下的所有类名，根据 psr-4 方式
     *
     * @param  string              $dir               目录
     * @param  string              $base_namespace    基类命名空间
     * @param  null|mixed          $rule              规则回调
     * @param  bool|string         $return_path_value 是否返回路径对应的数组，默认只返回类名列表
     * @throws FileSystemException
     */
    public static function getClassesPsr4(string $dir, string $base_namespace, mixed $rule = null, bool|string $return_path_value = false): array
    {
        $classes = [];
        // 扫描目录，使用递归模式，相对路径模式，因为下面此路径要用作转换成namespace
        $files = FileSystem::scanDirFiles($dir, true, true);
        if ($files === false) {
            throw new FileSystemException('Cannot scan dir files during get classes psr-4 from dir: ' . $dir);
        }
        foreach ($files as $v) {
            $pathinfo = pathinfo($v);
            if (($pathinfo['extension'] ?? '') == 'php') {
                if ($rule === null) {
                    if (file_exists($dir . '/' . $pathinfo['basename'] . '.ignore')) {
                        continue;
                    }
                    if (mb_substr($pathinfo['basename'], 0, 7) == 'global_' || mb_substr($pathinfo['basename'], 0, 7) == 'script_') {
                        continue;
                    }
                } elseif (is_callable($rule) && !$rule($dir, $pathinfo)) {
                    continue;
                }
                $dirname = $pathinfo['dirname'] == '.' ? '' : (str_replace('/', '\\', $pathinfo['dirname']) . '\\');
                $class_name = $base_namespace . '\\' . $dirname . $pathinfo['filename'];
                if (is_string($return_path_value)) {
                    $classes[$class_name] = $return_path_value . '/' . $v;
                } else {
                    $classes[] = $class_name;
                }
            }
        }
        return $classes;
    }

    /**
     * 删除目录及目录下的所有文件（危险操作）
     *
     * @throws FileSystemException
     */
    public static function removeDir(string $dir): bool
    {
        $dir = FileSystem::convertPath($dir);
        logger()->debug('Removing path recursively: "' . $dir . '"');
        // 不是目录不扫，直接 false 处理
        if (!file_exists($dir)) {
            logger()->debug('Scan dir failed, no such file or directory.');
            return false;
        }
        if (!is_dir($dir)) {
            logger()->warning('Scan dir failed, not directory.');
            return false;
        }
        logger()->debug('scanning directory ' . $dir);
        // 套上 zm_dir
        $scan_list = scandir($dir);
        if ($scan_list === false) {
            logger()->warning('Scan dir failed, cannot scan directory: ' . $dir);
            return false;
        }
        // 遍历目录
        foreach ($scan_list as $v) {
            // Unix 系统排除这俩目录
            if ($v == '.' || $v == '..') {
                continue;
            }
            $sub_file = self::convertPath($dir . '/' . $v);
            if (is_dir($sub_file)) {
                # 如果是 目录 且 递推 , 则递推添加下级文件
                if (!self::removeDir($sub_file)) {
                    return false;
                }
            } elseif (is_link($sub_file) || is_file($sub_file)) {
                if (!unlink($sub_file)) {
                    return false;
                }
            }
        }
        if (is_link($dir)) {
            return unlink($dir);
        }
        return rmdir($dir);
    }

    /**
     * @throws FileSystemException
     */
    public static function createDir(string $path): void
    {
        if (!is_dir($path) && !f_mkdir($path, 0755, true) && !is_dir($path)) {
            throw new FileSystemException(sprintf('Unable to create dir: %s', $path));
        }
    }

    /**
     * @param  mixed               ...$args Arguments passed to file_put_contents
     * @throws FileSystemException
     */
    public static function writeFile(string $path, mixed $content, ...$args): bool|int|string
    {
        $dir = pathinfo(self::convertPath($path), PATHINFO_DIRNAME);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new FileSystemException('Write file failed, cannot create parent directory: ' . $dir);
        }
        return file_put_contents($path, $content, ...$args);
    }

    /**
     * Reset (remove recursively and create again) dir
     *
     * @throws FileSystemException
     */
    public static function resetDir(string $dir_name): void
    {
        if (is_dir($dir_name)) {
            self::removeDir($dir_name);
        }
        self::createDir($dir_name);
    }

    public static function addSourceExtractHook(string $name, callable $callback): void
    {
        self::$_extract_hook[$name][] = $callback;
    }

    /**
     * Check whether the path is a relative path (judging according to whether the first character is "/")
     *
     * @param string $path Path
     */
    public static function isRelativePath(string $path): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return !(strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':');
        }
        return strlen($path) > 0 && $path[0] !== '/';
    }

    public static function replacePathVariable(string $path): string
    {
        $replacement = [
            '{pkg_root_path}' => PKG_ROOT_PATH,
            '{php_sdk_path}' => getenv('PHP_SDK_PATH') ? getenv('PHP_SDK_PATH') : WORKING_DIR . '/php-sdk-binary-tools',
            '{working_dir}' => WORKING_DIR,
            '{download_path}' => DOWNLOAD_PATH,
            '{source_path}' => SOURCE_PATH,
        ];
        return str_replace(array_keys($replacement), array_values($replacement), $path);
    }

    public static function backupFile(string $path): string
    {
        copy($path, $path . '.bak');
        return $path . '.bak';
    }

    public static function restoreBackupFile(string $path): void
    {
        if (!file_exists($path . '.bak')) {
            throw new RuntimeException('Cannot find bak file for ' . $path);
        }
        copy($path . '.bak', $path);
        unlink($path . '.bak');
    }

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    private static function extractArchive(string $filename, string $target): void
    {
        // Git source, just move
        if (is_dir(self::convertPath($filename))) {
            self::copyDir(self::convertPath($filename), $target);
            return;
        }
        // Create base dir
        if (f_mkdir(directory: $target, recursive: true) !== true) {
            throw new FileSystemException('create ' . $target . ' dir failed');
        }
        if (!file_exists($filename)) {
            throw new FileSystemException('File not exists');
        }

        if (in_array(PHP_OS_FAMILY, ['Darwin', 'Linux', 'BSD'])) {
            match (self::extname($filename)) {
                'tar', 'xz', 'txz' => f_passthru("tar -xf {$filename} -C {$target} --strip-components 1"),
                'tgz', 'gz' => f_passthru("tar -xzf {$filename} -C {$target} --strip-components 1"),
                'bz2' => f_passthru("tar -xjf {$filename} -C {$target} --strip-components 1"),
                'zip' => f_passthru("unzip {$filename} -d {$target}"),
                default => throw new FileSystemException('unknown archive format: ' . $filename),
            };
        } elseif (PHP_OS_FAMILY === 'Windows') {
            // use php-sdk-binary-tools/bin/7za.exe
            $_7z = self::convertPath(getenv('PHP_SDK_PATH') . '/bin/7za.exe');

            // Windows notes: I hate windows tar.......
            // When extracting .tar.gz like libxml2, it shows a symlink error and returns code[1].
            // Related posts: https://answers.microsoft.com/en-us/windows/forum/all/tar-on-windows-fails-to-extract-archive-containing/0ee9a7ea-9b1f-4fef-86a9-5d9dc35cea2f
            // And MinGW tar.exe cannot work on temporarily storage ??? (GitHub Actions hosted runner)
            // Yeah, I will be an MS HATER !
            match (self::extname($filename)) {
                'tar' => f_passthru("tar -xf {$filename} -C {$target} --strip-components 1"),
                'xz', 'txz', 'gz', 'tgz', 'bz2' => cmd()->execWithResult("\"{$_7z}\" x -so {$filename} | tar -f - -x -C \"{$target}\" --strip-components 1"),
                'zip' => f_passthru("\"{$_7z}\" x {$filename} -o{$target} -y"),
                default => throw new FileSystemException("unknown archive format: {$filename}"),
            };
        }
    }

    /**
     * @throws FileSystemException
     */
    private static function replaceFile(string $filename, int $replace_type = REPLACE_FILE_STR, mixed $callback_or_search = null, mixed $to_replace = null): false|int
    {
        logger()->debug('Replacing file with type[' . $replace_type . ']: ' . $filename);
        $file = self::readFile($filename);
        switch ($replace_type) {
            case REPLACE_FILE_STR:
            default:
                $file = str_replace($callback_or_search, $to_replace, $file);
                break;
            case REPLACE_FILE_PREG:
                $file = preg_replace($callback_or_search, $to_replace, $file);
                break;
            case REPLACE_FILE_USER:
                $file = $callback_or_search($file);
                break;
        }
        return file_put_contents($filename, $file);
    }

    private static function emitSourceExtractHook(string $name, string $target): void
    {
        foreach ((self::$_extract_hook[$name] ?? []) as $hook) {
            if ($hook($name, $target) === true) {
                logger()->info('Patched source [' . $name . '] after extracted');
            }
        }
    }
}
