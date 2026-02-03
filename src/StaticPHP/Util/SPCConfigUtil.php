<?php

declare(strict_types=1);

namespace StaticPHP\Util;

use StaticPHP\Config\PackageConfig;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Runtime\SystemTarget;

class SPCConfigUtil
{
    private bool $no_php;

    private bool $libs_only_deps;

    private bool $absolute_libs;

    /**
     * @param array{
     *     no_php?: bool,
     *     libs_only_deps?: bool,
     *     absolute_libs?: bool
     * } $options Options pass to spc-config
     */
    public function __construct(array $options = [])
    {
        $this->no_php = $options['no_php'] ?? false;
        $this->libs_only_deps = $options['libs_only_deps'] ?? false;
        $this->absolute_libs = $options['absolute_libs'] ?? false;
    }

    public function config(array $packages = [], bool $include_suggests = false): array
    {
        // if have php, make php as all extension's dependency
        if (!$this->no_php) {
            $dep_override = ['php' => array_filter($packages, fn ($y) => str_starts_with($y, 'ext-'))];
        } else {
            $dep_override = [];
        }
        $resolved = DependencyResolver::resolve($packages, $dep_override, $include_suggests);

        $ldflags = $this->getLdflagsString();
        $cflags = $this->getIncludesString($resolved);
        $libs = $this->getLibsString($resolved, !$this->absolute_libs);

        // additional OS-specific libraries (e.g. macOS -lresolv)
        // embed
        if ($extra_libs = SystemTarget::getRuntimeLibs()) {
            $libs .= " {$extra_libs}";
        }

        $extra_env = getenv('SPC_EXTRA_LIBS');
        if (is_string($extra_env) && !empty($extra_env)) {
            $libs .= " {$extra_env}";
        }
        // package frameworks
        if (SystemTarget::getTargetOS() === 'Darwin') {
            $libs .= " {$this->getFrameworksString($resolved)}";
        }
        // C++
        if ($this->hasCpp($resolved)) {
            $libcpp = SystemTarget::getTargetOS() === 'Darwin' ? '-lc++' : '-lstdc++';
            $libs = str_replace($libcpp, '', $libs) . " {$libcpp}";
        }

        if ($this->libs_only_deps) {
            // mimalloc must come first
            if (in_array('mimalloc', $resolved) && file_exists(BUILD_LIB_PATH . '/libmimalloc.a')) {
                $libs = BUILD_LIB_PATH . '/libmimalloc.a ' . str_replace([BUILD_LIB_PATH . '/libmimalloc.a', '-lmimalloc'], ['', ''], $libs);
            }
            return [
                'cflags' => clean_spaces(getenv('CFLAGS') . ' ' . $cflags),
                'ldflags' => clean_spaces(getenv('LDFLAGS') . ' ' . $ldflags),
                'libs' => clean_spaces(getenv('LIBS') . ' ' . $libs),
            ];
        }

        // embed
        if (!$this->no_php) {
            $libs = "-lphp {$libs} -lc";
        }

        $allLibs = getenv('LIBS') . ' ' . $libs;

        // mimalloc must come first
        if (in_array('mimalloc', $resolved) && file_exists(BUILD_LIB_PATH . '/libmimalloc.a')) {
            $allLibs = BUILD_LIB_PATH . '/libmimalloc.a ' . str_replace([BUILD_LIB_PATH . '/libmimalloc.a', '-lmimalloc'], ['', ''], $allLibs);
        }

        return [
            'cflags' => clean_spaces(getenv('CFLAGS') . ' ' . $cflags),
            'ldflags' => clean_spaces(getenv('LDFLAGS') . ' ' . $ldflags),
            'libs' => clean_spaces($allLibs),
        ];
    }

    /**
     * [Helper function]
     * Get configuration for a specific extension(s) dependencies.
     *
     * @param array|PhpExtensionPackage $extension_packages Extension instance or list
     * @return array{
     *     cflags: string,
     *     ldflags: string,
     *     libs: string
     * }
     */
    public function getExtensionConfig(array|PhpExtensionPackage $extension_packages, bool $include_suggests = false): array
    {
        if (!is_array($extension_packages)) {
            $extension_packages = [$extension_packages];
        }
        return $this->config(
            packages: array_map(fn ($y) => $y->getName(), $extension_packages),
            include_suggests: $include_suggests,
        );
    }

    /**
     * [Helper function]
     * Get configuration for a specific library(s) dependencies.
     *
     * @param array|LibraryPackage $lib              Library instance or list
     * @param bool                 $include_suggests Whether to include suggested libraries
     * @return array{
     *     cflags: string,
     *     ldflags: string,
     *     libs: string
     * }
     */
    public function getLibraryConfig(array|LibraryPackage $lib, bool $include_suggests = false): array
    {
        if (!is_array($lib)) {
            $lib = [$lib];
        }
        $save_no_php = $this->no_php;
        $this->no_php = true;
        $save_libs_only_deps = $this->libs_only_deps;
        $this->libs_only_deps = true;
        $ret = $this->config(
            packages: array_map(fn ($y) => $y->getName(), $lib),
            include_suggests: $include_suggests,
        );
        $this->no_php = $save_no_php;
        $this->libs_only_deps = $save_libs_only_deps;
        return $ret;
    }

    /**
     * Get build configuration for a package and its sub-dependencies within a resolved set.
     *
     * This is useful when you need to statically link something against a specific
     * library and all its transitive dependencies. It properly handles optional
     * dependencies by only including those that were actually resolved.
     *
     * @param string   $package_name      The package to get config for
     * @param string[] $resolved_packages The full resolved package list
     * @param bool     $include_suggests  Whether to include resolved suggests
     * @return array{
     *     cflags: string,
     *     ldflags: string,
     *     libs: string
     * }
     */
    public function getPackageDepsConfig(string $package_name, array $resolved_packages, bool $include_suggests = false): array
    {
        // Get sub-dependencies within the resolved set
        $sub_deps = DependencyResolver::getSubDependencies($package_name, $resolved_packages, $include_suggests);

        if (empty($sub_deps)) {
            return [
                'cflags' => '',
                'ldflags' => '',
                'libs' => '',
            ];
        }

        // Use libs_only_deps mode and no_php for library linking
        $save_no_php = $this->no_php;
        $save_libs_only_deps = $this->libs_only_deps;
        $this->no_php = true;
        $this->libs_only_deps = true;

        $ret = $this->configWithResolvedPackages($sub_deps);

        $this->no_php = $save_no_php;
        $this->libs_only_deps = $save_libs_only_deps;

        return $ret;
    }

    /**
     * Get configuration using already-resolved packages (skip dependency resolution).
     *
     * @param string[] $resolved_packages Already resolved package names in build order
     * @return array{
     *     cflags: string,
     *     ldflags: string,
     *     libs: string
     * }
     */
    public function configWithResolvedPackages(array $resolved_packages): array
    {
        $ldflags = $this->getLdflagsString();
        $cflags = $this->getIncludesString($resolved_packages);
        $libs = $this->getLibsString($resolved_packages, !$this->absolute_libs);

        // additional OS-specific libraries (e.g. macOS -lresolv)
        if ($extra_libs = SystemTarget::getRuntimeLibs()) {
            $libs .= " {$extra_libs}";
        }

        $extra_env = getenv('SPC_EXTRA_LIBS');
        if (is_string($extra_env) && !empty($extra_env)) {
            $libs .= " {$extra_env}";
        }

        // package frameworks
        if (SystemTarget::getTargetOS() === 'Darwin') {
            $libs .= " {$this->getFrameworksString($resolved_packages)}";
        }

        // C++
        if ($this->hasCpp($resolved_packages)) {
            $libcpp = SystemTarget::getTargetOS() === 'Darwin' ? '-lc++' : '-lstdc++';
            $libs = str_replace($libcpp, '', $libs) . " {$libcpp}";
        }

        if ($this->libs_only_deps) {
            // mimalloc must come first
            if (in_array('mimalloc', $resolved_packages) && file_exists(BUILD_LIB_PATH . '/libmimalloc.a')) {
                $libs = BUILD_LIB_PATH . '/libmimalloc.a ' . str_replace([BUILD_LIB_PATH . '/libmimalloc.a', '-lmimalloc'], ['', ''], $libs);
            }
            return [
                'cflags' => clean_spaces(getenv('CFLAGS') . ' ' . $cflags),
                'ldflags' => clean_spaces(getenv('LDFLAGS') . ' ' . $ldflags),
                'libs' => clean_spaces(getenv('LIBS') . ' ' . $libs),
            ];
        }

        // embed
        if (!$this->no_php) {
            $libs = "-lphp {$libs} -lc";
        }

        $allLibs = getenv('LIBS') . ' ' . $libs;

        // mimalloc must come first
        if (in_array('mimalloc', $resolved_packages) && file_exists(BUILD_LIB_PATH . '/libmimalloc.a')) {
            $allLibs = BUILD_LIB_PATH . '/libmimalloc.a ' . str_replace([BUILD_LIB_PATH . '/libmimalloc.a', '-lmimalloc'], ['', ''], $allLibs);
        }

        return [
            'cflags' => clean_spaces(getenv('CFLAGS') . ' ' . $cflags),
            'ldflags' => clean_spaces(getenv('LDFLAGS') . ' ' . $ldflags),
            'libs' => clean_spaces($allLibs),
        ];
    }

    private function hasCpp(array $packages): bool
    {
        foreach ($packages as $package) {
            $lang = PackageConfig::get($package, 'lang', 'c');
            if ($lang === 'cpp') {
                return true;
            }
        }
        return false;
    }

    private function getIncludesString(array $packages): string
    {
        $base = BUILD_INCLUDE_PATH;
        $includes = ["-I{$base}"];

        // link with libphp
        if (!$this->no_php) {
            $includes = [
                ...$includes,
                "-I{$base}/php",
                "-I{$base}/php/main",
                "-I{$base}/php/TSRM",
                "-I{$base}/php/Zend",
                "-I{$base}/php/ext",
            ];
        }

        // parse pkg-configs
        foreach ($packages as $package) {
            $pc = PackageConfig::get($package, 'pkg-configs', []);
            foreach ($pc as $file) {
                if (!file_exists(BUILD_LIB_PATH . "/pkgconfig/{$file}.pc")) {
                    throw new WrongUsageException("pkg-config file '{$file}.pc' for lib [{$package}] does not exist in '" . BUILD_LIB_PATH . "/pkgconfig'. Please build it first.");
                }
            }
            $pc_cflags = implode(' ', $pc);
            if ($pc_cflags !== '' && ($pc_cflags = PkgConfigUtil::getCflags($pc_cflags)) !== '') {
                $arr = explode(' ', $pc_cflags);
                $arr = array_unique($arr);
                $arr = array_filter($arr, fn ($x) => !str_starts_with($x, 'SHELL:-Xarch_'));
                $pc_cflags = implode(' ', $arr);
                $includes[] = $pc_cflags;
            }
        }
        $includes = array_unique($includes);
        return implode(' ', $includes);
    }

    private function getLdflagsString(): string
    {
        return '-L' . BUILD_LIB_PATH;
    }

    private function getLibsString(array $packages, bool $use_short_libs = true): string
    {
        $lib_names = [];
        $frameworks = [];

        foreach ($packages as $package) {
            // parse pkg-configs only for unix systems
            if (SystemTarget::isUnix()) {
                // add pkg-configs libs
                $pkg_configs = PackageConfig::get($package, 'pkg-configs', []);
                foreach ($pkg_configs as $pkg_config) {
                    if (!file_exists(BUILD_LIB_PATH . "/pkgconfig/{$pkg_config}.pc")) {
                        throw new WrongUsageException("pkg-config file '{$pkg_config}.pc' for lib [{$package}] does not exist in '" . BUILD_LIB_PATH . "/pkgconfig'. Please build it first.");
                    }
                }
                $pkg_configs = implode(' ', $pkg_configs);
                if ($pkg_configs !== '') {
                    // static libs with dependencies come in reverse order, so reverse this too
                    $pc_libs = array_reverse(PkgConfigUtil::getLibsArray($pkg_configs));
                    $lib_names = [...$lib_names, ...$pc_libs];
                }
            }
            // convert all static-libs to short names
            $libs = array_reverse(PackageConfig::get($package, 'static-libs', []));
            foreach ($libs as $lib) {
                if (FileSystem::isRelativePath($lib)) {
                    // check file existence
                    if (!file_exists(BUILD_LIB_PATH . "/{$lib}")) {
                        throw new WrongUsageException("Library file '{$lib}' for lib [{$package}] does not exist in '" . BUILD_LIB_PATH . "'. Please build it first.");
                    }
                    $lib_names[] = $this->getShortLibName($lib);
                } else {
                    $lib_names[] = $lib;
                }
            }
            // add frameworks for macOS
            if (SystemTarget::getTargetOS() === 'Darwin') {
                $frameworks = array_merge($frameworks, PackageConfig::get($package, 'frameworks', []));
            }
        }

        // post-process
        $lib_names = array_filter($lib_names, fn ($x) => $x !== '');
        $lib_names = array_reverse(array_unique($lib_names));
        $frameworks = array_unique($frameworks);

        // process frameworks to short_name
        if (SystemTarget::getTargetOS() === 'Darwin') {
            foreach ($frameworks as $fw) {
                $ks = '-framework ' . $fw;
                if (!in_array($ks, $lib_names)) {
                    $lib_names[] = $ks;
                }
            }
        }

        if (in_array('imap', $packages) && SystemTarget::getTargetOS() === 'Linux' && SystemTarget::getLibc() === 'glibc') {
            $lib_names[] = '-lcrypt';
        }
        if (!$use_short_libs) {
            $lib_names = array_map(fn ($l) => $this->getFullLibName($l), $lib_names);
        }
        return implode(' ', $lib_names);
    }

    private function getShortLibName(string $lib): string
    {
        if (!str_starts_with($lib, 'lib') || !str_ends_with($lib, '.a')) {
            return BUILD_LIB_PATH . '/' . $lib;
        }
        // get short name
        return '-l' . substr($lib, 3, -2);
    }

    private function getFullLibName(string $lib): string
    {
        if (!str_starts_with($lib, '-l')) {
            return $lib;
        }
        $libname = substr($lib, 2);
        $staticLib = BUILD_LIB_PATH . '/' . "lib{$libname}.a";
        if (file_exists($staticLib)) {
            return $staticLib;
        }
        return $lib;
    }

    private function getFrameworksString(array $extensions): string
    {
        $list = [];
        foreach ($extensions as $extension) {
            foreach (PackageConfig::get($extension, 'frameworks', []) as $fw) {
                $ks = '-framework ' . $fw;
                if (!in_array($ks, $list)) {
                    $list[] = $ks;
                }
            }
        }
        return implode(' ', $list);
    }
}
