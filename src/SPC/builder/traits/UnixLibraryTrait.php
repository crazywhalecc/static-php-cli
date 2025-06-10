<?php

declare(strict_types=1);

namespace SPC\builder\traits;

use SPC\builder\LibraryBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;

trait UnixLibraryTrait
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    public function getStaticLibFiles(string $style = 'autoconf', bool $recursive = true, bool $include_self = true): string
    {
        $libs = $include_self ? [$this] : [];
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

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function makeAutoconfEnv(?string $prefix = null): string
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
    public function patchPkgconfPrefix(array $files, int $patch_option = PKGCONF_PATCH_ALL, ?array $custom_replace = null): void
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
            $file = ($patch_option & PKGCONF_PATCH_PREFIX) === PKGCONF_PATCH_PREFIX ? preg_replace('/^prefix\s*=.*$/m', 'prefix=${pcfiledir}/../..', $file) : $file;
            $file = ($patch_option & PKGCONF_PATCH_EXEC_PREFIX) === PKGCONF_PATCH_EXEC_PREFIX ? preg_replace('/^exec_prefix\s*=.*$/m', 'exec_prefix=${prefix}', $file) : $file;
            $file = ($patch_option & PKGCONF_PATCH_LIBDIR) === PKGCONF_PATCH_LIBDIR ? preg_replace('/^libdir\s*=.*$/m', 'libdir=${prefix}/lib', $file) : $file;
            $file = ($patch_option & PKGCONF_PATCH_INCLUDEDIR) === PKGCONF_PATCH_INCLUDEDIR ? preg_replace('/^includedir\s*=.*$/m', 'includedir=${prefix}/include', $file) : $file;
            $file = ($patch_option & PKGCONF_PATCH_CUSTOM) === PKGCONF_PATCH_CUSTOM && $custom_replace !== null ? preg_replace($custom_replace[0], $custom_replace[1], $file) : $file;
            FileSystem::writeFile($realpath, $file);
        }
    }

    public function patchLaDependencyPrefix(?array $files = null): void
    {
        logger()->info('Patching library [' . static::NAME . '] la files');
        $throwOnMissing = true;
        if ($files === null) {
            $files = $this->getStaticLibs();
            $files = array_map(fn ($name) => str_replace('.a', '.la', $name), $files);
            $throwOnMissing = false;
        }
        foreach ($files as $name) {
            $realpath = realpath(BUILD_LIB_PATH . '/' . $name);
            if ($realpath === false) {
                if ($throwOnMissing) {
                    throw new RuntimeException('Cannot find library [' . static::NAME . '] la file [' . $name . '] !');
                }
                logger()->warning('Cannot find library [' . static::NAME . '] la file [' . $name . '] !');
                continue;
            }
            logger()->debug('Patching ' . $realpath);
            // replace prefix
            $file = FileSystem::readFile($realpath);
            $file = str_replace(
                ' /lib/',
                ' ' . BUILD_LIB_PATH . '/',
                $file
            );
            $file = preg_replace('/^libdir=.*$/m', "libdir='" . BUILD_LIB_PATH . "'", $file);
            FileSystem::writeFile($realpath, $file);
        }
    }

    public function getLibExtraCFlags(): string
    {
        $env = getenv($this->getSnakeCaseName() . '_CFLAGS') ?: '';
        if (!str_contains($env, $this->builder->arch_c_flags)) {
            $env .= $this->builder->arch_c_flags;
        }
        return $env;
    }

    public function getLibExtraLdFlags(): string
    {
        return getenv($this->getSnakeCaseName() . '_LDFLAGS') ?: '';
    }

    public function getLibExtraLibs(): string
    {
        return getenv($this->getSnakeCaseName() . '_LIBS') ?: '';
    }

    public function getLibExtraCXXFlags(): string
    {
        $env = getenv($this->getSnakeCaseName() . '_CXXFLAGS') ?: '';
        if (!str_contains($env, $this->builder->arch_cxx_flags)) {
            $env .= $this->builder->arch_cxx_flags;
        }
        return $env;
    }
}
