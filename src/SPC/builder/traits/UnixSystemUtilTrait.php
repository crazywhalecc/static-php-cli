<?php

declare(strict_types=1);

namespace SPC\builder\traits;

use SPC\exception\ExecutionException;
use SPC\exception\SPCInternalException;
use SPC\exception\WrongUsageException;
use SPC\toolchain\ToolchainManager;
use SPC\toolchain\ZigToolchain;
use SPC\util\SPCTarget;

trait UnixSystemUtilTrait
{
    /**
     * Export static library dynamic symbols to a .dynsym file.
     * It will export to "/path/to/libxxx.a.dynsym".
     *
     * @param string $lib_file Static library file path (e.g. /path/to/libxxx.a)
     */
    public static function exportDynamicSymbols(string $lib_file): void
    {
        // check
        if (!is_file($lib_file)) {
            throw new WrongUsageException("The lib archive file {$lib_file} does not exist, please build it first.");
        }
        // shell out
        $cmd = 'nm -g --defined-only -P ' . escapeshellarg($lib_file);
        $result = shell()->execWithResult($cmd);
        if ($result[0] !== 0) {
            throw new ExecutionException($cmd, 'Failed to get defined symbols from ' . $lib_file);
        }
        // parse shell output and filter
        $defined = [];
        foreach ($result[1] as $line) {
            $line = trim($line);
            if ($line === '' || str_ends_with($line, '.o:') || str_ends_with($line, '.o]:')) {
                continue;
            }
            $name = strtok($line, " \t");
            if (!$name) {
                continue;
            }
            $name = preg_replace('/@.*$/', '', $name);
            if ($name !== '' && $name !== false) {
                $defined[] = $name;
            }
        }
        $defined = array_unique($defined);
        sort($defined);
        // export
        if (SPCTarget::getTargetOS() === 'Linux') {
            file_put_contents("{$lib_file}.dynsym", "{\n" . implode("\n", array_map(fn ($x) => "  {$x};", $defined)) . "};\n");
        } else {
            file_put_contents("{$lib_file}.dynsym", implode("\n", $defined) . "\n");
        }
    }

    /**
     * Get linker flag to export dynamic symbols from a static library.
     *
     * @param  string      $lib_file Static library file path (e.g. /path/to/libxxx.a)
     * @return null|string Linker flag to export dynamic symbols, null if no .dynsym file found
     */
    public static function getDynamicExportedSymbols(string $lib_file): ?string
    {
        $symbol_file = "{$lib_file}.dynsym";
        if (!is_file($symbol_file)) {
            self::exportDynamicSymbols($lib_file);
        }
        if (!is_file($symbol_file)) {
            throw new SPCInternalException("The symbol file {$symbol_file} does not exist, please check if nm command is available.");
        }
        // macOS/zig
        if (SPCTarget::getTargetOS() !== 'Linux' || ToolchainManager::getToolchainClass() === ZigToolchain::class) {
            return "-Wl,-exported_symbols_list,{$symbol_file}";
        }
        return "-Wl,--dynamic-list={$symbol_file}";
    }

    /**
     * Find a command in given paths or system PATH.
     * If $name is an absolute path, check if it exists.
     *
     * @param  string      $name  Command name or absolute path
     * @param  array       $paths Paths to search, if empty, use system PATH
     * @return null|string Absolute path of the command if found, null otherwise
     */
    public static function findCommand(string $name, array $paths = []): ?string
    {
        if (!$paths) {
            $paths = explode(PATH_SEPARATOR, getenv('PATH'));
        }
        if (str_starts_with($name, '/')) {
            return file_exists($name) ? $name : null;
        }
        foreach ($paths as $path) {
            if (file_exists($path . DIRECTORY_SEPARATOR . $name)) {
                return $path . DIRECTORY_SEPARATOR . $name;
            }
        }
        return null;
    }

    /**
     * Make environment variable string for shell command.
     *
     * @param  array  $vars Variables, like: ["CFLAGS" => "-Ixxx"]
     * @return string like: CFLAGS="-Ixxx"
     */
    public static function makeEnvVarString(array $vars): string
    {
        $str = '';
        foreach ($vars as $key => $value) {
            if ($str !== '') {
                $str .= ' ';
            }
            $str .= $key . '=' . escapeshellarg($value);
        }
        return $str;
    }
}
