<?php

declare(strict_types=1);

namespace StaticPHP\Util;

use StaticPHP\Exception\FileSystemException;

class FileSystem
{
    /**
     * Read file contents and throw exception on failure
     *
     * @param  string $filename The file path to read
     * @return string The file contents
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
     * Replace string content in file
     *
     * @param  string    $filename The file path
     * @param  mixed     $search   The search string
     * @param  mixed     $replace  The replacement string
     * @return false|int Number of replacements or false on failure
     */
    public static function replaceFileStr(string $filename, mixed $search = null, mixed $replace = null): false|int
    {
        return self::replaceFile($filename, REPLACE_FILE_STR, $search, $replace);
    }

    /**
     * Replace content in file using regex
     *
     * @param  string    $filename The file path
     * @param  mixed     $search   The regex pattern
     * @param  mixed     $replace  The replacement string
     * @return false|int Number of replacements or false on failure
     */
    public static function replaceFileRegex(string $filename, mixed $search = null, mixed $replace = null): false|int
    {
        return self::replaceFile($filename, REPLACE_FILE_PREG, $search, $replace);
    }

    /**
     * Replace content in file using custom callback
     *
     * @param  string    $filename The file path
     * @param  mixed     $callback The callback function
     * @return false|int Number of replacements or false on failure
     */
    public static function replaceFileUser(string $filename, mixed $callback = null): false|int
    {
        return self::replaceFile($filename, REPLACE_FILE_USER, $callback);
    }

    /**
     * Get file extension from filename
     *
     * @param  string $fn The filename
     * @return string The file extension (without dot)
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
     * Find command path in system PATH (similar to which command)
     *
     * @param  string      $name  The command name
     * @param  array       $paths Optional array of paths to search
     * @return null|string The full path to the command or null if not found
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
     * Copy directory recursively
     *
     * @param string $from Source directory path
     * @param string $to   Destination directory path
     */
    public static function copyDir(string $from, string $to): void
    {
        logger()->debug("Copying directory from {$from} to {$to}");
        $dst_path = FileSystem::convertPath($to);
        $src_path = FileSystem::convertPath($from);
        switch (PHP_OS_FAMILY) {
            case 'Windows':
                cmd(false)->exec('xcopy "' . $src_path . '" "' . $dst_path . '" /s/e/v/y/i');
                break;
            case 'Linux':
            case 'Darwin':
            case 'BSD':
                f_passthru('cp -r "' . $src_path . '" "' . $dst_path . '"');
                break;
        }
    }

    /**
     * Copy file from one location to another.
     * This method will throw an exception if the copy operation fails.
     *
     * @param string $from Source file path
     * @param string $to   Destination file path
     */
    public static function copy(string $from, string $to): bool
    {
        logger()->debug("Copying file from {$from} to {$to}");
        $dst_path = FileSystem::convertPath($to);
        $src_path = FileSystem::convertPath($from);
        if (!copy($src_path, $dst_path)) {
            throw new FileSystemException('Cannot copy file from ' . $src_path . ' to ' . $dst_path);
        }
        return true;
    }

    /**
     * Convert path to system-specific format
     *
     * @param  string $path The path to convert
     * @return string The converted path
     */
    public static function convertPath(string $path): string
    {
        if (str_starts_with($path, 'phar://')) {
            return $path;
        }
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Convert Windows path to MinGW format
     *
     * @param  string $path The Windows path
     * @return string The MinGW format path
     */
    public static function convertWinPathToMinGW(string $path): string
    {
        if (preg_match('/^[A-Za-z]:/', $path)) {
            $path = '/' . strtolower($path[0]) . '/' . str_replace('\\', '/', substr($path, 2));
        }
        return $path;
    }

    /**
     * Scan directory files recursively
     *
     * @param  string      $dir         Directory to scan
     * @param  bool        $recursive   Whether to scan recursively
     * @param  bool|string $relative    Whether to return relative paths
     * @param  bool        $include_dir Whether to include directories in result
     * @return array|false Array of files or false on failure
     */
    public static function scanDirFiles(string $dir, bool $recursive = true, bool|string $relative = false, bool $include_dir = false): array|false
    {
        $dir = self::convertPath($dir);
        if (!is_dir($dir)) {
            return false;
        }
        logger()->debug('scanning directory ' . $dir);
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
                $sub_list = self::scanDirFiles($sub_file, $recursive, $relative);
                if (is_array($sub_list)) {
                    foreach ($sub_list as $item) {
                        $list[] = $item;
                    }
                }
            } elseif (is_file($sub_file) || (is_dir($sub_file) && !$recursive && $include_dir)) {
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
     * Get PSR-4 classes from directory
     *
     * @param  string      $dir               Directory to scan
     * @param  string      $base_namespace    Base namespace
     * @param  mixed       $rule              Optional filtering rule
     * @param  bool|string $return_path_value Whether to return path as value
     * @param  bool        $auto_require      Whether to auto-require files (useful for external plugins)
     * @return array       Array of class names or class=>path pairs
     */
    public static function getClassesPsr4(string $dir, string $base_namespace, mixed $rule = null, bool|string $return_path_value = false, bool $auto_require = false): array
    {
        $classes = [];
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
                $file_path = self::convertPath($dir . '/' . $v);

                // Auto-require file if class is not loaded (for external plugins not in composer autoload)
                if ($auto_require && !class_exists($class_name, false)) {
                    require_once $file_path;
                }
                if (class_exists($class_name, false) === false) {
                    continue;
                }

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
     * Remove directory recursively
     *
     * @param  string $dir Directory to remove
     * @return bool   Success status
     */
    public static function removeDir(string $dir): bool
    {
        $dir = self::convertPath($dir);
        logger()->debug('Removing path recursively: "' . $dir . '"');
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
            InteractiveTerm::advance();
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
                    $cmd = PHP_OS_FAMILY === 'Windows' ? 'del /f /q' : 'rm -f';
                    f_exec("{$cmd} " . escapeshellarg($sub_file), $out, $ret);
                    if ($ret !== 0) {
                        logger()->warning('Remove file failed: ' . $sub_file);
                        return false;
                    }
                }
            }
        }
        if (is_link($dir)) {
            return @unlink($dir);
        }
        return @rmdir($dir);
    }

    /**
     * Create directory recursively
     *
     * @param string $path Directory path to create
     */
    public static function createDir(string $path): void
    {
        if (!is_dir($path) && !f_mkdir($path, 0755, true) && !is_dir($path)) {
            throw new FileSystemException(sprintf('Unable to create dir: %s', $path));
        }
    }

    /**
     * Write content to file
     *
     * @param  string          $path    File path
     * @param  mixed           $content Content to write
     * @param  mixed           ...$args Additional arguments passed to file_put_contents
     * @return bool|int|string Result of file writing operation
     */
    public static function writeFile(string $path, mixed $content, ...$args): bool|int|string
    {
        $dir = pathinfo(self::convertPath($path), PATHINFO_DIRNAME);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new FileSystemException('Write file failed, cannot create parent directory: ' . $dir);
        }
        return file_put_contents($path, $content, ...$args);
    }

    /**
     * Reset directory by removing and recreating it
     *
     * @param string $dir_name Directory name
     */
    public static function resetDir(string $dir_name): void
    {
        $dir_name = self::convertPath($dir_name);
        if (is_dir($dir_name)) {
            self::removeDir($dir_name);
        }
        self::createDir($dir_name);
    }

    /**
     * Check if path is relative
     *
     * @param  string $path Path to check
     * @return bool   True if path is relative
     */
    public static function isRelativePath(string $path): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return !(strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':');
        }
        return strlen($path) > 0 && $path[0] !== '/';
    }

    /**
     * Replace path variables with actual values
     *
     * @param  string $path Path with variables
     * @return string Path with replaced variables
     */
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

    /**
     * Create backup of file
     *
     * @param  string $path File path
     * @return string Backup file path
     */
    public static function backupFile(string $path): string
    {
        copy($path, $path . '.bak');
        return $path . '.bak';
    }

    /**
     * Restore file from backup
     *
     * @param string $path Original file path
     */
    public static function restoreBackupFile(string $path): void
    {
        if (!file_exists($path . '.bak')) {
            throw new FileSystemException("Backup restore failed: Cannot find bak file for {$path}");
        }
        copy($path . '.bak', $path);
        unlink($path . '.bak');
    }

    /**
     * Remove file if it exists
     *
     * @param string $string File path
     */
    public static function removeFileIfExists(string $string): void
    {
        $string = self::convertPath($string);
        if (file_exists($string)) {
            unlink($string);
        }
    }

    /**
     * Replace line in file that contains specific string
     *
     * @param  string    $file File path
     * @param  string    $find String to find in line
     * @param  string    $line New line content
     * @return false|int Number of replacements or false on failure
     */
    public static function replaceFileLineContainsString(string $file, string $find, string $line): false|int
    {
        $lines = file($file);
        if ($lines === false) {
            throw new FileSystemException('Cannot read file: ' . $file);
        }
        foreach ($lines as $key => $value) {
            if (str_contains($value, $find)) {
                $lines[$key] = $line . PHP_EOL;
            }
        }
        return file_put_contents($file, implode('', $lines));
    }

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
}
