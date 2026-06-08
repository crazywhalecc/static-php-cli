<?php

declare(strict_types=1);

namespace SPC\builder\traits;

use SPC\exception\PatchException;
use SPC\store\Config;
use SPC\store\FileSystem;
use SPC\util\SPCConfigUtil;

trait UnixLibraryTrait
{
    public function getStaticLibFiles(bool $include_self = true): string
    {
        $libs = $include_self ? [$this] : [];
        array_unshift($libs, ...array_values($this->getDependencies(recursive: true)));
        $config = new SPCConfigUtil($this->builder, options: ['libs_only_deps' => true, 'absolute_libs' => true]);
        $res = $config->config(libraries: array_map(fn ($x) => $x->getName(), $libs));
        return $res['libs'];
    }

    /**
     * Patch pkgconfig file prefix, exec_prefix, libdir, includedir for correct build.
     *
     * @param array      $files          File list to patch, if empty, will use pkg-configs from config (e.g. ['zlib.pc', 'openssl.pc'])
     * @param int        $patch_option   Patch options
     * @param null|array $custom_replace Custom replace rules, if provided, will be used to replace in the format [regex, replacement]
     */
    public function patchPkgconfPrefix(array $files = [], int $patch_option = PKGCONF_PATCH_ALL, ?array $custom_replace = null): void
    {
        logger()->info('Patching library [' . static::NAME . '] pkgconfig');
        if ($files === [] && ($conf_pc = Config::getLib(static::NAME, 'pkg-configs', [])) !== []) {
            $files = array_map(fn ($x) => "{$x}.pc", $conf_pc);
        }
        foreach ($files as $name) {
            $realpath = realpath(BUILD_LIB_PATH . '/pkgconfig/' . $name);
            if ($realpath === false) {
                throw new PatchException('pkg-config prefix patcher', 'Cannot find library [' . static::NAME . '] pkgconfig file [' . $name . '] in ' . BUILD_LIB_PATH . '/pkgconfig/ !');
            }
            logger()->debug('Patching ' . $realpath);
            // replace prefix
            $file = FileSystem::readFile($realpath);
            $file = ($patch_option & PKGCONF_PATCH_PREFIX) === PKGCONF_PATCH_PREFIX ? preg_replace('/^prefix\s*=.*$/m', 'prefix=' . BUILD_ROOT_PATH, $file) : $file;
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
                    throw new PatchException('la dependency patcher', 'Cannot find library [' . static::NAME . '] la file [' . $name . '] !');
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
            $env .= ' ' . $this->builder->arch_c_flags;
        }
        return trim($env);
    }

    public function getLibExtraCXXFlags(): string
    {
        $env = getenv($this->getSnakeCaseName() . '_CXXFLAGS') ?: '';
        if (!str_contains($env, $this->builder->arch_cxx_flags)) {
            $env .= ' ' . $this->builder->arch_cxx_flags;
        }
        return trim($env);
    }

    public function getLibExtraLdFlags(): string
    {
        $env = getenv($this->getSnakeCaseName() . '_LDFLAGS') ?: '';
        if (!str_contains($env, $this->builder->arch_ld_flags)) {
            $env .= ' ' . $this->builder->arch_ld_flags;
        }
        return trim($env);
    }

    public function getLibExtraLibs(): string
    {
        return getenv($this->getSnakeCaseName() . '_LIBS') ?: '';
    }
}
