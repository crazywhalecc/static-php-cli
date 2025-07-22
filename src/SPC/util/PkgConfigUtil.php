<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\RuntimeException;

class PkgConfigUtil
{
    /**
     * Returns --cflags-only-other output.
     * The reason we return the string is we cannot use array_unique() on cflags,
     * some cflags may contains spaces.
     *
     * @param  string           $pkg_config_str .pc file str, accepts multiple files
     * @return string           cflags string, e.g. "-Wno-implicit-int-float-conversion ..."
     * @throws RuntimeException
     */
    public static function getCflags(string $pkg_config_str): string
    {
        // get other things
        $result = self::execWithResult("pkg-config --static --cflags-only-other {$pkg_config_str}");
        return trim($result);
    }

    /**
     * Returns --libs-only-l and --libs-only-other output.
     * The reason we return the array is to avoid duplicate lib defines.
     *
     * @param  string           $pkg_config_str .pc file str, accepts multiple files
     * @return array            Unique libs array, e.g. [-lz, -lxml, ...]
     * @throws RuntimeException
     */
    public static function getLibsArray(string $pkg_config_str): array
    {
        // Use this instead of shell() to avoid unnecessary outputs
        $result = self::execWithResult("pkg-config --static --libs-only-l {$pkg_config_str}");
        $libs = explode(' ', trim($result));

        // get other things
        $result = self::execWithResult("pkg-config --libs --libs-only-other {$pkg_config_str}");
        // convert libxxx.a to -L{path} -lxxx
        $exp = explode(' ', trim($result));
        foreach ($exp as $item) {
            // if item ends with .a, convert it to -lxxx
            if (str_ends_with($item, '.a') && str_starts_with($item, 'lib')) {
                $name = pathinfo($item, PATHINFO_BASENAME);
                $name = substr($name, 3, -2); // remove 'lib' prefix and '.a' suffix
                $libs[] = "-l{$name}";
            } else {
                // if item starts with -L, keep it as is
                // if item starts with -l, keep it as is
                $libs[] = $item;
            }
        }

        return array_unique($libs);
    }

    private static function execWithResult(string $cmd): string
    {
        f_exec($cmd, $output, $result_code);
        if ($result_code !== 0) {
            throw new RuntimeException("pkg-config command failed with code {$result_code}: {$cmd}");
        }
        return implode("\n", $output);
    }
}
