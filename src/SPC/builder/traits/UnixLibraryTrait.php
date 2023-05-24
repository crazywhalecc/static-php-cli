<?php

declare(strict_types=1);

namespace SPC\builder\traits;

use SPC\builder\LibraryBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait UnixLibraryTrait
{
    use LibraryTrait;

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function getStaticLibFiles(string $style = 'autoconf', bool $recursive = true): string
    {
        $libs = [$this];
        if ($recursive) {
            array_unshift($libs, ...array_values($this->getDependencies(recursive: true)));
        }

        $sep = match ($style) {
            'autoconf' => ' ',
            'cmake' => ';',
            default => throw new RuntimeException('style only support autoconf and cmake'),
        };
        $ret = [];
        /** @var LibraryBase $lib */
        foreach ($libs as $lib) {
            $libFiles = [];
            foreach ($lib->getStaticLibs() as $name) {
                $name = str_replace(' ', '\ ', FileSystem::convertPath(BUILD_LIB_PATH . "/{$name}"));
                $name = str_replace('"', '\"', $name);
                $libFiles[] = $name;
            }
            array_unshift($ret, implode($sep, $libFiles));
        }
        return implode($sep, $ret);
    }

    public function makeAutoconfEnv(string $prefix = null): string
    {
        if ($prefix === null) {
            $prefix = str_replace('-', '_', strtoupper(static::NAME));
        }
        return $prefix . '_CFLAGS="-I' . BUILD_INCLUDE_PATH . '" ' .
            $prefix . '_LIBS="' . $this->getStaticLibFiles() . '"';
    }

    /**
     * Patch pkgconfig file prefix
     *
     * @param  array               $files file list
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function patchPkgconfPrefix(array $files, int $patch_option = PKGCONF_PATCH_ALL): void
    {
        logger()->info('Patching library [' . static::NAME . '] pkgconfig');
        foreach ($files as $name) {
            $realpath = realpath(BUILD_ROOT_PATH . '/lib/pkgconfig/' . $name);
            if ($realpath === false) {
                throw new RuntimeException('Cannot find library [' . static::NAME . '] pkgconfig file [' . $name . '] !');
            }
            logger()->debug('Patching ' . $realpath);
            // replace prefix
            $file = FileSystem::readFile($realpath);
            $file = ($patch_option & PKGCONF_PATCH_PREFIX) === PKGCONF_PATCH_PREFIX ? preg_replace('/^prefix=.*$/m', 'prefix=' . BUILD_ROOT_PATH, $file) : $file;
            $file = ($patch_option & PKGCONF_PATCH_EXEC_PREFIX) === PKGCONF_PATCH_EXEC_PREFIX ? preg_replace('/^exec_prefix=.*$/m', 'exec_prefix=${prefix}', $file) : $file;
            $file = ($patch_option & PKGCONF_PATCH_LIBDIR) === PKGCONF_PATCH_LIBDIR ? preg_replace('/^libdir=.*$/m', 'libdir=${prefix}/lib', $file) : $file;
            $file = ($patch_option & PKGCONF_PATCH_INCLUDEDIR) === PKGCONF_PATCH_INCLUDEDIR ? preg_replace('/^includedir=.*$/m', 'includedir=${prefix}/include', $file) : $file;
            FileSystem::writeFile($realpath, $file);
        }
    }

    /**
     * remove libtool archive files
     *
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function cleanLaFiles(): void
    {
        foreach ($this->getStaticLibs() as $lib) {
            $filename = pathinfo($lib, PATHINFO_FILENAME) . '.la';
            if (file_exists(BUILD_LIB_PATH . '/' . $filename)) {
                unlink(BUILD_LIB_PATH . '/' . $filename);
            }
        }
    }
}
