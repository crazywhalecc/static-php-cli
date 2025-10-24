<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\exception\FileSystemException;
use SPC\exception\SPCException;

class FileSystem
{
    private static array $_extract_hook = [];

    /**
     * Load configuration array from JSON file
     *
     * @param  string      $config     The configuration name (ext, lib, source, pkg, pre-built)
     * @param  null|string $config_dir Optional custom config directory
     * @return array       The loaded configuration array
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
     * Copy file from one location to another.
     * This method will throw an exception if the copy operation fails.
     *
     * @param string $from Source file path
     * @param string $to   Destination file path
     */
    public static function copy(string $from, string $to): void
    {
        logger()->debug("Copying file from {$from} to {$to}");
        $dst_path = FileSystem::convertPath($to);
        $src_path = FileSystem::convertPath($from);
        if (!copy($src_path, $dst_path)) {
            throw new FileSystemException('Cannot copy file from ' . $src_path . ' to ' . $dst_path);
        }
    }

    /**
     * Extract package archive to specified directory
     *
     * @param string      $name         Package name
     * @param string      $source_type  Archive type (tar.gz, zip, etc.)
     * @param string      $filename     Archive filename
     * @param null|string $extract_path Optional extraction path
     */
    public static function extractPackage(string $name, string $source_type, string $filename, ?string $extract_path = null): void
    {
        if ($extract_path !== null) {
            // replace
            $extract_path = self::replacePathVariable($extract_path);
            $extract_path = self::isRelativePath($extract_path) ? (WORKING_DIR . '/' . $extract_path) : $extract_path;
        } else {
            $extract_path = PKG_ROOT_PATH . '/' . $name;
        }
        logger()->info("Extracting {$name} package to {$extract_path} ...");
        $target = self::convertPath($extract_path);

        if (!is_dir($dir = dirname($target))) {
            self::createDir($dir);
        }
        try {
            // extract wrapper command
            self::extractWithType($source_type, $filename, $extract_path);
        } catch (SPCException $e) {
            if (PHP_OS_FAMILY === 'Windows') {
                f_passthru('rmdir /s /q ' . $target);
            } else {
                f_passthru('rm -rf ' . $target);
            }
            throw new FileSystemException("Cannot extract package {$name}", $e->getCode(), $e);
        }
    }

    /**
     * Extract source archive to source directory
     *
     * @param string      $name        Source name
     * @param string      $source_type Archive type (tar.gz, zip, etc.)
     * @param string      $filename    Archive filename
     * @param null|string $move_path   Optional move path
     */
    public static function extractSource(string $name, string $source_type, string $filename, ?string $move_path = null): void
    {
        // if source hook is empty, load it
        if (self::$_extract_hook === []) {
            SourcePatcher::init();
        }
        $move_path = match ($move_path) {
            null => SOURCE_PATH . '/' . $name,
            default => self::isRelativePath($move_path) ? (SOURCE_PATH . '/' . $move_path) : $move_path,
        };
        $target = self::convertPath($move_path);
        logger()->info("Extracting {$name} source to {$target}" . ' ...');
        if (!is_dir($dir = dirname($target))) {
            self::createDir($dir);
        }
        try {
            self::extractWithType($source_type, $filename, $move_path);
            self::emitSourceExtractHook($name, $target);
        } catch (SPCException $e) {
            if (PHP_OS_FAMILY === 'Windows') {
                f_passthru('rmdir /s /q ' . $target);
            } else {
                f_passthru('rm -rf ' . $target);
            }
            throw new FileSystemException('Cannot extract source ' . $name . ': ' . $e->getMessage(), $e->getCode(), $e);
        }
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
     * @return array       Array of class names or class=>path pairs
     */
    public static function getClassesPsr4(string $dir, string $base_namespace, mixed $rule = null, bool|string $return_path_value = false): array
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
        $dir = FileSystem::convertPath($dir);
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
     * Add source extraction hook
     *
     * @param string   $name     Source name
     * @param callable $callback Callback function
     */
    public static function addSourceExtractHook(string $name, callable $callback): void
    {
        self::$_extract_hook[$name][] = $callback;
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

    private static function extractArchive(string $filename, string $target): void
    {
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
                'zip' => self::unzipWithStrip($filename, $target),
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
                'zip' => self::unzipWithStrip($filename, $target),
                default => throw new FileSystemException("unknown archive format: {$filename}"),
            };
        }
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

    private static function emitSourceExtractHook(string $name, string $target): void
    {
        foreach ((self::$_extract_hook[$name] ?? []) as $hook) {
            if ($hook($name, $target) === true) {
                logger()->info('Patched source [' . $name . '] after extracted');
            }
        }
    }

    private static function extractWithType(string $source_type, string $filename, string $extract_path): void
    {
        logger()->debug("Extracting source [{$source_type}]: {$filename}");
        /* @phpstan-ignore-next-line */
        match ($source_type) {
            SPC_SOURCE_ARCHIVE => self::extractArchive($filename, $extract_path),
            SPC_SOURCE_GIT => self::copyDir(self::convertPath($filename), $extract_path),
            // soft link to the local source
            SPC_SOURCE_LOCAL => symlink(self::convertPath($filename), $extract_path),
        };
    }

    /**
     * Move file or directory, handling cross-device scenarios
     * Uses rename() if possible, falls back to copy+delete for cross-device moves
     *
     * @param string $source Source path
     * @param string $dest   Destination path
     */
    private static function moveFileOrDir(string $source, string $dest): void
    {
        $source = self::convertPath($source);
        $dest = self::convertPath($dest);

        // Try rename first (fast, atomic)
        if (@rename($source, $dest)) {
            return;
        }

        if (is_dir($source)) {
            self::copyDir($source, $dest);
            self::removeDir($source);
        } else {
            if (!copy($source, $dest)) {
                throw new FileSystemException("Failed to copy file from {$source} to {$dest}");
            }
            if (!unlink($source)) {
                throw new FileSystemException("Failed to remove source file: {$source}");
            }
        }
    }

    /**
     * Unzip file with stripping top-level directory
     */
    private static function unzipWithStrip(string $zip_file, string $extract_path): void
    {
        $temp_dir = self::convertPath(sys_get_temp_dir() . '/spc_unzip_' . bin2hex(random_bytes(16)));
        $zip_file = self::convertPath($zip_file);
        $extract_path = self::convertPath($extract_path);

        // extract to temp dir
        self::createDir($temp_dir);

        if (PHP_OS_FAMILY === 'Windows') {
            $mute = defined('DEBUG_MODE') ? '' : ' > NUL';
            // use php-sdk-binary-tools/bin/7za.exe
            $_7z = self::convertPath(getenv('PHP_SDK_PATH') . '/bin/7za.exe');
            f_passthru("\"{$_7z}\" x {$zip_file} -o{$temp_dir} -y{$mute}");
        } else {
            $mute = defined('DEBUG_MODE') ? '' : ' > /dev/null';
            f_passthru("unzip \"{$zip_file}\" -d \"{$temp_dir}\"{$mute}");
        }
        // scan first level dirs (relative, not recursive, include dirs)
        $contents = self::scanDirFiles($temp_dir, false, true, true);
        if ($contents === false) {
            throw new FileSystemException('Cannot scan unzip temp dir: ' . $temp_dir);
        }
        // if extract path already exists, remove it
        if (is_dir($extract_path)) {
            self::removeDir($extract_path);
        }
        // if only one dir, move its contents to extract_path
        $subdir = self::convertPath("{$temp_dir}/{$contents[0]}");
        if (count($contents) === 1 && is_dir($subdir)) {
            self::moveFileOrDir($subdir, $extract_path);
        } else {
            // else, if it contains only one dir, strip dir and copy other files
            $dircount = 0;
            $dir = [];
            $top_files = [];
            foreach ($contents as $item) {
                if (is_dir(self::convertPath("{$temp_dir}/{$item}"))) {
                    ++$dircount;
                    $dir[] = $item;
                } else {
                    $top_files[] = $item;
                }
            }
            // extract dir contents to extract_path
            self::createDir($extract_path);
            // extract move dir
            if ($dircount === 1) {
                $sub_contents = self::scanDirFiles("{$temp_dir}/{$dir[0]}", false, true, true);
                if ($sub_contents === false) {
                    throw new FileSystemException("Cannot scan unzip temp sub-dir: {$dir[0]}");
                }
                foreach ($sub_contents as $sub_item) {
                    self::moveFileOrDir(self::convertPath("{$temp_dir}/{$dir[0]}/{$sub_item}"), self::convertPath("{$extract_path}/{$sub_item}"));
                }
            } else {
                foreach ($dir as $item) {
                    self::moveFileOrDir(self::convertPath("{$temp_dir}/{$item}"), self::convertPath("{$extract_path}/{$item}"));
                }
            }
            // move top-level files to extract_path
            foreach ($top_files as $top_file) {
                self::moveFileOrDir(self::convertPath("{$temp_dir}/{$top_file}"), self::convertPath("{$extract_path}/{$top_file}"));
            }
        }

        // Clean up temp directory
        self::removeDir($temp_dir);
    }
}
