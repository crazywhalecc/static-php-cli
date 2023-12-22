<?php

namespace SPC\builder\windows;

class SystemUtil
{
    /**
     * @param  string      $name  命令名称
     * @param  array       $paths 寻找的目标路径（如果不传入，则使用环境变量 PATH）
     * @return null|string 找到了返回命令路径，找不到返回 null
     */
    public static function findCommand(string $name, array $paths = []): ?string
    {
        if (!$paths) {
            $paths = explode(PATH_SEPARATOR, getenv('Path'));
        }
        foreach ($paths as $path) {
            if (file_exists($path . DIRECTORY_SEPARATOR . $name)) {
                return $path . DIRECTORY_SEPARATOR . $name;
            }
        }
        return null;
    }
}