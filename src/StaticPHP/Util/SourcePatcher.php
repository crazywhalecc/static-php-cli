<?php

declare(strict_types=1);

namespace StaticPHP\Util;

use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Exception\PatchException;
use StaticPHP\Registry\PackageLoader;

/**
 * SourcePatcher provides static utility methods for patching source files.
 */
class SourcePatcher
{
    /**
     * Use existing patch file for patching.
     *
     * @param  string $patch_name Patch file name in src/globals/patch/ or absolute path
     * @param  string $cwd        Working directory for patch command
     * @param  bool   $reverse    Reverse patches (default: false)
     * @return bool   True if patch was applied or already applied
     */
    public static function patchFile(string $patch_name, string $cwd, bool $reverse = false): bool
    {
        logger()->debug('Applying ' . ($reverse ? 'reverse ' : '') . "patch [{$patch_name}] at [{$cwd}]");
        if (FileSystem::isRelativePath($patch_name)) {
            $patch_file = ROOT_DIR . "/src/globals/patch/{$patch_name}";
        } else {
            $patch_file = $patch_name;
        }

        if (!file_exists($patch_file)) {
            throw new PatchException($patch_name, "Patch file [{$patch_file}] does not exist");
        }

        $patch_str = FileSystem::convertPath($patch_file);
        if (!file_exists($patch_str)) {
            throw new PatchException($patch_name, "Patch file [{$patch_str}] does not exist");
        }

        // Copy patch from phar
        if (str_starts_with($patch_str, 'phar://')) {
            $filename = pathinfo($patch_file, PATHINFO_BASENAME);
            file_put_contents(SOURCE_PATH . "/{$filename}", file_get_contents($patch_file));
            $patch_str = FileSystem::convertPath(SOURCE_PATH . "/{$filename}");
        }

        // Detect if patch is already applied (reverse detection)
        $detect_reverse = !$reverse;
        $detect_cmd = 'cd ' . escapeshellarg($cwd) . ' && '
            . (PHP_OS_FAMILY === 'Windows' ? 'type' : 'cat') . ' ' . escapeshellarg($patch_str)
            . ' | patch --dry-run -p1 -s -f ' . ($detect_reverse ? '-R' : '')
            . ' > ' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null') . ' 2>&1';
        exec($detect_cmd, $output, $detect_status);

        if ($detect_status === 0) {
            // Patch already applied
            return true;
        }

        // Apply patch
        $apply_cmd = 'cd ' . escapeshellarg($cwd) . ' && '
            . (PHP_OS_FAMILY === 'Windows' ? 'type' : 'cat') . ' ' . escapeshellarg($patch_str)
            . ' | patch -p1 ' . ($reverse ? '-R' : '');

        exec($apply_cmd, $apply_output, $apply_status);
        if ($apply_status !== 0) {
            throw new PatchException($patch_name, "Patch file [{$patch_name}] failed to apply");
        }

        return true;
    }

    /**
     * Patch hardcoded INI values into PHP SAPI files.
     *
     * @param  string $php_source_dir PHP source directory path
     * @param  array  $ini            Associative array of INI key-value pairs
     * @return bool   True if patch was applied successfully
     */
    #[PatchDescription('Patch hardcoded INI values into PHP SAPI files')]
    public static function patchHardcodedINI(string $php_source_dir, array $ini = []): bool
    {
        $sapi_files = [
            'cli' => "{$php_source_dir}/sapi/cli/php_cli.c",
            'micro' => "{$php_source_dir}/sapi/micro/php_micro.c",
            'embed' => "{$php_source_dir}/sapi/embed/php_embed.c",
        ];

        // Build patch string
        $find_str = 'const char HARDCODED_INI[] =';
        $patch_str = '';
        foreach ($ini as $key => $value) {
            $patch_str .= "\"{$key}={$value}\\n\"\n";
        }
        $patch_str = "const char HARDCODED_INI[] =\n{$patch_str}";

        // Detect and restore from backup if exists
        $has_backup = false;
        foreach ($sapi_files as $file) {
            if (file_exists("{$file}.bak")) {
                $has_backup = true;
                break;
            }
        }
        if ($has_backup) {
            self::unpatchHardcodedINI($php_source_dir);
        }

        // Backup and patch each SAPI file
        $result = true;
        foreach ($sapi_files as $file) {
            if (!file_exists($file)) {
                continue;
            }
            // Backup
            $result = $result && file_put_contents("{$file}.bak", file_get_contents($file)) !== false;
            // Patch
            FileSystem::replaceFileStr($file, $find_str, $patch_str);
        }

        return $result;
    }

    /**
     * Restore PHP SAPI files from backup (unpatch hardcoded INI).
     *
     * @param  string $php_source_dir PHP source directory path
     * @return bool   True if backup was restored successfully
     */
    public static function unpatchHardcodedINI(string $php_source_dir): bool
    {
        $sapi_files = [
            'cli' => "{$php_source_dir}/sapi/cli/php_cli.c",
            'micro' => "{$php_source_dir}/sapi/micro/php_micro.c",
            'embed' => "{$php_source_dir}/sapi/embed/php_embed.c",
        ];

        $has_backup = false;
        foreach ($sapi_files as $file) {
            if (file_exists("{$file}.bak")) {
                $has_backup = true;
                break;
            }
        }

        if (!$has_backup) {
            return false;
        }

        $result = true;
        foreach ($sapi_files as $file) {
            $backup = "{$file}.bak";
            if (file_exists($backup)) {
                $result = $result && file_put_contents($file, file_get_contents($backup)) !== false;
                @unlink($backup);
            }
        }

        return $result;
    }

    /**
     * Patch micro SAPI to support compressed phar loading from the current executable.
     *
     * @param int $version_id PHP version ID
     */
    public static function patchMicroPhar(int $version_id): void
    {
        FileSystem::backupFile(SOURCE_PATH . '/php-src/ext/phar/phar.c');
        FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/ext/phar/phar.c',
            'static zend_op_array *phar_compile_file',
            "char *micro_get_filename(void);\n\nstatic zend_op_array *phar_compile_file"
        );
        if ($version_id < 80100) {
            // PHP 8.0.x
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/ext/phar/phar.c',
                'if (strstr(file_handle->filename, ".phar") && !strstr(file_handle->filename, "://")) {',
                'if ((strstr(file_handle->filename, micro_get_filename()) || strstr(file_handle->filename, ".phar")) && !strstr(file_handle->filename, "://")) {'
            );
        } else {
            // PHP >= 8.1
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/ext/phar/phar.c',
                'if (strstr(ZSTR_VAL(file_handle->filename), ".phar") && !strstr(ZSTR_VAL(file_handle->filename), "://")) {',
                'if ((strstr(ZSTR_VAL(file_handle->filename), micro_get_filename()) || strstr(ZSTR_VAL(file_handle->filename), ".phar")) && !strstr(ZSTR_VAL(file_handle->filename), "://")) {'
            );
        }
    }

    public static function unpatchMicroPhar(): void
    {
        FileSystem::restoreBackupFile(SOURCE_PATH . '/php-src/ext/phar/phar.c');
    }

    public static function patchPhpSrc(?array $items = null): bool
    {
        $patch_dir = ROOT_DIR . '/src/globals/patch/php-src-patches';
        // in phar mode, we need to extract all the patch files
        if (str_starts_with($patch_dir, 'phar://')) {
            $tmp_dir = sys_get_temp_dir() . '/php-src-patches';
            FileSystem::createDir($tmp_dir);
            foreach (FileSystem::scanDirFiles($patch_dir) as $file) {
                FileSystem::writeFile("{$tmp_dir}/" . basename($file), file_get_contents($file));
            }
            $patch_dir = $tmp_dir;
        }
        $php_package = PackageLoader::getTargetPackage('php');
        if (!file_exists("{$php_package->getSourceDir()}/sapi/micro/php_micro.c")) {
            return false;
        }
        $ver_file = "{$php_package->getSourceDir()}/main/php_version.h";
        if (!file_exists($ver_file)) {
            throw new PatchException('php-src patcher (original micro patches)', 'Patch failed, cannot find php source files');
        }
        $version_h = FileSystem::readFile("{$php_package->getSourceDir()}/main/php_version.h");
        preg_match('/#\s*define\s+PHP_MAJOR_VERSION\s+(\d+)\s+#\s*define\s+PHP_MINOR_VERSION\s+(\d+)\s+/m', $version_h, $match);
        // $ver = "{$match[1]}.{$match[2]}";

        $major_ver = $match[1] . $match[2];
        if ($major_ver === '74') {
            return false;
        }
        // $check = !defined('DEBUG_MODE') ? ' -q' : '';
        // f_passthru('cd ' . SOURCE_PATH . '/php-src && git checkout' . $check . ' HEAD');

        if ($items !== null) {
            $spc_micro_patches = $items;
        } else {
            $spc_micro_patches = getenv('SPC_MICRO_PATCHES');
            $spc_micro_patches = $spc_micro_patches === false ? [] : explode(',', $spc_micro_patches);
        }
        $spc_micro_patches = array_filter($spc_micro_patches, fn ($item) => trim((string) $item) !== '');
        $patch_list = $spc_micro_patches;
        $patches = [];
        $serial = ['80', '81', '82', '83', '84', '85'];
        foreach ($patch_list as $patchName) {
            if (file_exists("{$patch_dir}/{$patchName}.patch")) {
                $patches[] = "{$patch_dir}/{$patchName}.patch";
                continue;
            }
            for ($i = array_search($major_ver, $serial, true); $i >= 0; --$i) {
                $tryMajMin = $serial[$i];
                if (!file_exists("{$patch_dir}/{$patchName}_{$tryMajMin}.patch")) {
                    continue;
                }
                $patches[] = "{$patch_dir}/{$patchName}_{$tryMajMin}.patch";
                continue 2;
            }
            throw new PatchException('phpmicro patches', "Failed finding patch file or versioned file {$patchName} !");
        }

        foreach ($patches as $patch) {
            logger()->info("Patching micro with {$patch}");
            self::patchFile($patch, $php_package->getSourceDir());
        }

        return true;
    }
}
