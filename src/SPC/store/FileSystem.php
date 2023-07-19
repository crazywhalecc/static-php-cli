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
    public static function loadConfigArray(string $config): array
    {
        $whitelist = ['ext', 'lib', 'source'];
        if (!in_array($config, $whitelist)) {
            throw new FileSystemException('Reading ' . $config . '.json is not allowed');
        }
        $tries = [
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
    public static function replaceFile(string $filename, int $replace_type = REPLACE_FILE_STR, mixed $callback_or_search = null, mixed $to_replace = null): bool|int
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
                f_passthru('cp -r "' . $src_path . '" "' . $dst_path . '"');
                break;
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
        }
        logger()->info("extracting {$name} source");
        try {
            $target = $move_path ?? (SOURCE_PATH . "/{$name}");
            // Git source, just move
            if (is_dir($filename)) {
                self::copyDir($filename, $target);
                self::emitSourceExtractHook($name);
                return;
            }

            if (PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'Linux') {
                if (f_mkdir(directory: $target, recursive: true) !== true) {
                    throw new FileSystemException('create ' . $name . 'source dir failed');
                }
                switch (self::extname($filename)) {
                    case 'xz':
                    case 'txz':
                        f_passthru("tar -xf {$filename} -C {$target} --strip-components 1");
                        // f_passthru("cat {$filename} | xz -d | tar -x -C " . SOURCE_PATH . "/{$name} --strip-components 1");
                        break;
                    case 'gz':
                    case 'tgz':
                        f_passthru("tar -xzf {$filename} -C {$target} --strip-components 1");
                        break;
                    case 'bz2':
                        f_passthru("tar -xjf {$filename} -C {$target} --strip-components 1");
                        break;
                    case 'zip':
                        f_passthru("unzip {$filename} -d {$target}");
                        break;
                        // case 'zstd':
                        // case 'zst':
                        //     passthru('cat ' . $filename . ' | zstd -d | tar -x -C ".SOURCE_PATH . "/' . $name . ' --strip-components 1', $ret);
                        //     break;
                    case 'tar':
                        f_passthru("tar -xf {$filename} -C {$target} --strip-components 1");
                        break;
                    default:
                        throw new FileSystemException('unknown archive format: ' . $filename);
                }
            } elseif (PHP_OS_FAMILY === 'Windows') {
                // find 7z
                $_7zExe = self::findCommandPath('7z', [
                    'C:\Program Files\7-Zip-Zstandard',
                    'C:\Program Files (x86)\7-Zip-Zstandard',
                    'C:\Program Files\7-Zip',
                    'C:\Program Files (x86)\7-Zip',
                ]);
                if (!$_7zExe) {
                    throw new FileSystemException('windows needs 7z to unpack');
                }
                f_mkdir(SOURCE_PATH . "/{$name}", recursive: true);
                switch (self::extname($filename)) {
                    case 'zstd':
                    case 'zst':
                        if (!str_contains($_7zExe, 'Zstandard')) {
                            throw new FileSystemException("zstd is not supported: {$filename}");
                        }
                        // no break
                    case 'xz':
                    case 'txz':
                    case 'gz':
                    case 'tgz':
                    case 'bz2':
                        f_passthru("\"{$_7zExe}\" x -so {$filename} | tar -f - -x -C {$target} --strip-components 1");
                        break;
                    case 'tar':
                        f_passthru("tar -xf {$filename} -C {$target} --strip-components 1");
                        break;
                    case 'zip':
                        f_passthru("\"{$_7zExe}\" x {$filename} -o{$target}");
                        break;
                    default:
                        throw new FileSystemException("unknown archive format: {$filename}");
                }
            }
        } catch (RuntimeException $e) {
            if (PHP_OS_FAMILY === 'Windows') {
                f_passthru('rmdir /s /q ' . SOURCE_PATH . "/{$name}");
            } else {
                f_passthru('rm -r ' . SOURCE_PATH . "/{$name}");
            }
            throw new FileSystemException('Cannot extract source ' . $name, $e->getCode(), $e);
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

    /**
     * 递归或非递归扫描目录，可返回相对目录的文件列表或绝对目录的文件列表
     *
     * @param  string      $dir         目录
     * @param  bool        $recursive   是否递归扫描子目录
     * @param  bool|string $relative    是否返回相对目录，如果为true则返回相对目录，如果为false则返回绝对目录
     * @param  bool        $include_dir 非递归模式下，是否包含目录
     * @return array|false
     * @since 2.5
     */
    public static function scanDirFiles(string $dir, bool $recursive = true, bool|string $relative = false, bool $include_dir = false): bool|array
    {
        $dir = self::convertPath($dir);
        // 不是目录不扫，直接 false 处理
        if (!is_dir($dir)) {
            logger()->warning('Scan dir failed, no such directory.');
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
                $path = rtrim($dir, '/') . '/' . rtrim($pathinfo['dirname'], './') . '/' . $pathinfo['basename'];

                // 过滤不包含类的文件
                $tokens = token_get_all(self::readFile($path));
                $found = false;
                foreach ($tokens as $token) {
                    if (!is_array($token)) {
                        continue;
                    }
                    if ($token[0] === T_CLASS) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    continue;
                }

                if ($rule === null) { // 规则未设置回调时候，使用默认的识别过滤规则
                    /*if (substr(file_get_contents($dir . '/' . $v), 6, 6) == '#plain') {
                        continue;
                    }*/
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
        if (!is_dir($dir)) {
            logger()->warning('Scan dir failed, no such directory.');
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
        return rmdir($dir);
    }

    public static function createDir(string $path): void
    {
        if (!is_dir($path) && !f_mkdir($path, 0755, true) && !is_dir($path)) {
            throw new FileSystemException(sprintf('无法建立目录：%s', $path));
        }
    }

    public static function writeFile(string $path, $content, ...$args): bool|string|int
    {
        $dir = pathinfo($path, PATHINFO_DIRNAME);
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

    public static function addSourceExtractHook(string $name, callable $callback)
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
        // 适配 Windows 的多盘符目录形式
        if (DIRECTORY_SEPARATOR === '\\') {
            return !(strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':');
        }
        return strlen($path) > 0 && $path[0] !== '/';
    }

    private static function emitSourceExtractHook(string $name)
    {
        foreach ((self::$_extract_hook[$name] ?? []) as $hook) {
            if ($hook() === true) {
                logger()->info('Patched source [' . $name . '] after extracted');
            }
        }
    }
}
