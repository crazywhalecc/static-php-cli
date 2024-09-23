<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\exception\FileSystemException;
use SPC\exception\WrongUsageException;

class PackageManager
{
    public static function installPackage(string $pkg_name, ?array $config = null, bool $force = false): void
    {
        if ($config === null) {
            $config = Config::getPkg($pkg_name);
        }
        if ($config === null) {
            $arch = arch2gnu(php_uname('m'));
            $os = match (PHP_OS_FAMILY) {
                'Linux' => 'linux',
                'Windows' => 'win',
                'BSD' => 'freebsd',
                'Darwin' => 'macos',
                default => throw new WrongUsageException('Unsupported OS!'),
            };
            $config = Config::getPkg("{$pkg_name}-{$arch}-{$os}");
            $pkg_name = "{$pkg_name}-{$arch}-{$os}";
        }
        if ($config === null) {
            throw new WrongUsageException("Package [{$pkg_name}] does not exist, please check the name and correct it !");
        }

        // Download package
        Downloader::downloadPackage($pkg_name, $config, $force);
        // After download, read lock file name
        $lock = json_decode(FileSystem::readFile(DOWNLOAD_PATH . '/.lock.json'), true);
        $filename = DOWNLOAD_PATH . '/' . ($lock[$pkg_name]['filename'] ?? $lock[$pkg_name]['dirname']);
        $extract = $lock[$pkg_name]['move_path'] === null ? (PKG_ROOT_PATH . '/' . $pkg_name) : $lock[$pkg_name]['move_path'];
        FileSystem::extractPackage($pkg_name, $filename, $extract);

        // if contains extract-files, we just move this file to destination, and remove extract dir
        if (is_array($config['extract-files'] ?? null) && is_assoc_array($config['extract-files'])) {
            $scandir = FileSystem::scanDirFiles($extract, true, true);
            foreach ($config['extract-files'] as $file => $target) {
                $target = FileSystem::convertPath(FileSystem::replacePathVariable($target));
                if (!is_dir($dir = dirname($target))) {
                    f_mkdir($dir, 0755, true);
                }
                logger()->debug("Moving package [{$pkg_name}] file {$file} to {$target}");
                // match pattern, needs to scan dir
                $file = FileSystem::convertPath($file);
                $found = false;
                foreach ($scandir as $item) {
                    if (match_pattern($file, $item)) {
                        $file = $item;
                        $found = true;
                        break;
                    }
                }
                if ($found === false) {
                    throw new FileSystemException('Unable to find extract-files item: ' . $file);
                }
                rename(FileSystem::convertPath($extract . '/' . $file), $target);
            }
            FileSystem::removeDir($extract);
        }
    }
}
