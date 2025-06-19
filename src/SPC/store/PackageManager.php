<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\exception\FileSystemException;
use SPC\exception\WrongUsageException;
use SPC\store\pkg\CustomPackage;

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
        if (Config::getPkg($pkg_name)['type'] === 'custom') {
            // Custom extract function
            $classes = FileSystem::getClassesPsr4(ROOT_DIR . '/src/SPC/store/pkg', 'SPC\store\pkg');
            foreach ($classes as $class) {
                if (is_a($class, CustomPackage::class, true) && $class !== CustomPackage::class) {
                    $cls = new $class();
                    if (in_array($pkg_name, $cls->getSupportName())) {
                        (new $class())->extract($pkg_name);
                        break;
                    }
                }
            }
            return;
        }
        // After download, read lock file name
        $lock = LockFile::get($pkg_name);
        $source_type = $lock['source_type'];
        $filename = LockFile::getLockFullPath($lock);
        $extract = LockFile::getExtractPath($pkg_name, PKG_ROOT_PATH . '/' . $pkg_name);

        FileSystem::extractPackage($pkg_name, $source_type, $filename, $extract);

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
