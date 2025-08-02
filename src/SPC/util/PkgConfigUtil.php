<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\RuntimeException;

/**
 * Utility class for pkg-config operations
 *
 * This class provides methods to interact with pkg-config to get
 * compilation flags and library information for building extensions.
 */
class PkgConfigUtil
{
    /**
     * Returns the version of a module.
     * This method uses `pkg-config --modversion` to get the version of the specified module.
     * If the module is not found, it will throw a RuntimeException.
     *
     * @param  string           $pkg_config_str .pc file str, accepts multiple files
     * @return string           version string, e.g. "1.2.3"
     * @throws RuntimeException
     */
    public static function getModuleVersion(string $pkg_config_str): string
    {
        $result = self::execWithResult("pkg-config --modversion {$pkg_config_str}");
        return trim($result);
    }

    /**
     * Get CFLAGS from pkg-config
     *
     * Returns --cflags-only-other output from pkg-config.
     * The reason we return the string is we cannot use array_unique() on cflags,
     * some cflags may contains spaces.
     *
     * @param  string           $pkg_config_str .pc file string, accepts multiple files
     * @return string           CFLAGS string, e.g. "-Wno-implicit-int-float-conversion ..."
     * @throws RuntimeException
     */
    public static function getCflags(string $pkg_config_str): string
    {
        // get other things
        $result = self::execWithResult("pkg-config --static --cflags-only-other {$pkg_config_str}");
        return trim($result);
    }

    /**
     * Get library flags from pkg-config
     *
     * Returns --libs-only-l and --libs-only-other output.
     * The reason we return the array is to avoid duplicate lib defines.
     *
     * @param  string           $pkg_config_str .pc file string, accepts multiple files
     * @return array            Unique libs array, e.g. [-lz, -lxml, ...]
     * @throws RuntimeException
     */
    public static function getLibsArray(string $pkg_config_str): array
    {
        // Use this instead of shell() to avoid unnecessary outputs
        $result = self::execWithResult("pkg-config --static --libs-only-l {$pkg_config_str}");
        $libs = explode(' ', trim($result));

        // get other things
        $result = self::execWithResult("pkg-config --static --libs-only-other {$pkg_config_str}");
        // convert libxxx.a to -L{path} -lxxx
        $exp = explode(' ', trim($result));
        foreach ($exp as $item) {
            if (str_starts_with($item, '-L')) {
                $libs[] = $item;
                continue;
            }
            // if item ends with .a, convert it to -lxxx
            if (str_ends_with($item, '.a') && (str_starts_with($item, 'lib') || str_starts_with($item, BUILD_LIB_PATH))) {
                $name = pathinfo($item, PATHINFO_BASENAME);
                $name = substr($name, 3, -2); // remove 'lib' prefix and '.a' suffix
                $shortlib = "-l{$name}";
                if (!in_array($shortlib, $libs)) {
                    $libs[] = $shortlib;
                }
            } elseif (!in_array($item, $libs)) {
                $libs[] = $item;
            }
        }

        // enhancement for linker
        return array_reverse(array_unique(array_reverse($libs)));
    }

    /**
     * Execute pkg-config command and return result
     *
     * @param  string           $cmd The pkg-config command to execute
     * @return string           The command output
     * @throws RuntimeException If command fails
     */
    private static function execWithResult(string $cmd): string
    {
        f_exec($cmd, $output, $result_code);
        if ($result_code !== 0) {
            throw new RuntimeException("pkg-config command failed with code {$result_code}: {$cmd}");
        }
        return implode("\n", $output);
    }
}
