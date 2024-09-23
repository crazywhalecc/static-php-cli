<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait libyaml
{
    public function getLibVersion(): ?string
    {
        // Match version from CMakeLists.txt:
        // Format: set (YAML_VERSION_MAJOR 0)
        // set (YAML_VERSION_MINOR 2)
        // set (YAML_VERSION_PATCH 5)
        $content = FileSystem::readFile($this->source_dir . '/CMakeLists.txt');
        if (preg_match('/set \(YAML_VERSION_MAJOR (\d+)\)/', $content, $major)
            && preg_match('/set \(YAML_VERSION_MINOR (\d+)\)/', $content, $minor)
            && preg_match('/set \(YAML_VERSION_PATCH (\d+)\)/', $content, $patch)) {
            return "{$major[1]}.{$minor[1]}.{$patch[1]}";
        }
        return null;
    }

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        [$lib, $include, $destdir] = SEPARATED_PATH;

        FileSystem::resetDir($this->source_dir . '/build');
        shell()->cd($this->source_dir . '/build')
            ->exec(
                'cmake ' .
                // '--debug-find ' .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DBUILD_TESTING=OFF ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DCMAKE_INSTALL_PREFIX=/ ' .
                "-DCMAKE_INSTALL_LIBDIR={$lib} " .
                "-DCMAKE_INSTALL_INCLUDEDIR={$include} " .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '..'
            )
            ->exec("make -j{$this->builder->concurrency}")
            ->exec("make install DESTDIR={$destdir}");
    }
}
