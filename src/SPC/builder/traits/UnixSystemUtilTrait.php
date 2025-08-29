<?php

declare(strict_types=1);

namespace SPC\builder\traits;

/**
 * Unix 系统的工具函数 Trait，适用于 Linux、macOS
 */
trait UnixSystemUtilTrait
{
    /**
     * @param  string      $name  命令名称
     * @param  array       $paths 寻找的目标路径（如果不传入，则使用环境变量 PATH）
     * @return null|string 找到了返回命令路径，找不到返回 null
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
