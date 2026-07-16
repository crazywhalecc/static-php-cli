<?php

declare(strict_types=1);

namespace StaticPHP\Util;

use StaticPHP\Config\PackageConfig;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\Package;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Package\TargetPackage;
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

    /**
     * Resolve a standalone package request and generate its configuration.
     *
     * This is the entry point for spc-config and callers that do not already have
     * a PackageInstaller resolution result.
     *
     * @param  array<Package|string>                                                  $packages
     * @return array{cflags: string, cxxflags: string, ldflags: string, libs: string}
     */
    public function config(array $packages = [], bool $include_suggests = false): array
    {
        // if have php, make php as all extension's dependency
        $package_names = array_map(fn ($package) => is_string($package) ? $package : $package->getName(), $packages);
        $dep_override = $this->no_php
            ? []
            : ['php' => array_values(array_filter($package_names, fn ($name) => str_starts_with($name, 'ext-')))];

        return $this->configWithResolvedPackages(
            DependencyResolver::resolve($packages, $dep_override, $include_suggests)
        );
    }

    /**
     * Build configuration for roots within the installer's resolved package graph.
     *
     * The installer decides whether suggested packages are enabled. This method always
     * walks depends and suggests, then filters every edge through that resolved set.
     *
     * @param  string[]                                                               $package_names Root package names whose link closure is required
     * @return array{cflags: string, cxxflags: string, ldflags: string, libs: string}
     */
    public function configForResolvedBuild(array $package_names, PackageInstaller $installer): array
    {
        $dependency_overrides = [];
        if (!$this->no_php) {
            $php_link_packages = [];
            foreach ($installer->getResolvedPackages(PhpExtensionPackage::class) as $extension) {
                if ($extension->isBuildStatic()) {
                    $php_link_packages[] = $extension->getName();
                }
            }
            foreach ($installer->getResolvedPackages(TargetPackage::class) as $target) {
                if ($target->isVirtualTarget()) {
                    $php_link_packages[] = $target->getName();
                }
            }
            $dependency_overrides['php'] = $php_link_packages;
        }

        return $this->configWithResolvedPackages(
            DependencyResolver::getResolvedPackageClosure(
                $package_names,
                array_keys($installer->getResolvedPackages()),
                $dependency_overrides,
            )
        );
    }

    /**
     * [Helper function]
     * Get configuration for a specific extension(s) dependencies.
     *
     * @param PhpExtensionPackage|PhpExtensionPackage[] $extension_packages Extension instance or list
     * @return array{
     *     cflags: string,
     *     cxxflags: string,
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
            packages: array_map(fn ($extension_package) => $extension_package->getName(), $extension_packages),
            include_suggests: $include_suggests,
        );
    }

    /**
     * [Helper function]
     * Get configuration for a specific library(s) dependencies.
     *
     * @param LibraryPackage|LibraryPackage[] $library_packages Library instance or list
     * @param bool                            $include_suggests Whether to include suggested libraries
     * @return array{
     *     cflags: string,
     *     cxxflags: string,
     *     ldflags: string,
     *     libs: string
     * }
     */
    public function getLibraryConfig(array|LibraryPackage $library_packages, bool $include_suggests = false): array
    {
        if (!is_array($library_packages)) {
            $library_packages = [$library_packages];
        }
        $save_no_php = $this->no_php;
        $this->no_php = true;
        $save_libs_only_deps = $this->libs_only_deps;
        $this->libs_only_deps = true;
        try {
            return $this->config(
                packages: array_map(fn ($library_package) => $library_package->getName(), $library_packages),
                include_suggests: $include_suggests,
            );
        } finally {
            $this->no_php = $save_no_php;
            $this->libs_only_deps = $save_libs_only_deps;
        }
    }

    /**
     * Get build configuration for a package and its sub-dependencies within a resolved set.
     *
     * This is useful when you need to statically link something against a specific
     * library and all its transitive dependencies. It properly handles optional
     * dependencies by only including those that were actually resolved.
     *
     * @param string   $package_name           The package to get config for
     * @param string[] $resolved_package_names The full resolved package name list
     * @param bool     $include_suggests       Whether to include resolved suggests
     * @return array{
     *     cflags: string,
     *     cxxflags: string,
     *     ldflags: string,
     *     libs: string
     * }
     */
    public function getPackageDepsConfig(string $package_name, array $resolved_package_names, bool $include_suggests = true): array
    {
        // Get sub-dependencies within the resolved set
        $sub_deps = DependencyResolver::getSubDependencies($package_name, $resolved_package_names, $include_suggests);

        if (empty($sub_deps)) {
            return [
                'cflags' => '',
                'cxxflags' => '',
                'ldflags' => '',
                'libs' => '',
            ];
        }

        // Use libs_only_deps mode and no_php for library linking
        $save_no_php = $this->no_php;
        $save_libs_only_deps = $this->libs_only_deps;
        $this->no_php = true;
        $this->libs_only_deps = true;

        try {
            return $this->configWithResolvedPackages($sub_deps);
        } finally {
            $this->no_php = $save_no_php;
            $this->libs_only_deps = $save_libs_only_deps;
        }
    }

    /**
     * Generate configuration from an exact, dependency-ordered package list.
     *
     * This low-level method does not resolve dependencies or filter packages.
     *
     * @param string[] $resolved_package_names Already resolved package names in build order
     * @return array{
     *     cflags: string,
     *     cxxflags: string,
     *     ldflags: string,
     *     libs: string
     * }
     */
    public function configWithResolvedPackages(array $resolved_package_names): array
    {
        $ldflags = $this->getLdflagsString();
        $includes = $this->getIncludesString($resolved_package_names);
        $libs = $this->getLibsString($resolved_package_names, !$this->absolute_libs);

        $cflags = deduplicate_flags(clean_spaces((getenv('SPC_DEFAULT_CFLAGS') ?: '') . ' ' . getenv('CFLAGS') . ' ' . $includes));
        $cxxflags = deduplicate_flags(clean_spaces((getenv('SPC_DEFAULT_CXXFLAGS') ?: '') . ' ' . getenv('CXXFLAGS') . ' ' . $includes));
        $ldflags = deduplicate_flags(clean_spaces((getenv('SPC_DEFAULT_LDFLAGS') ?: '') . ' ' . getenv('LDFLAGS') . ' ' . $ldflags));

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
            $libs .= " {$this->getFrameworksString($resolved_package_names)}";
        }

        // C++
        if ($this->hasCpp($resolved_package_names)) {
            $target_os = SystemTarget::getTargetOS();
            if ($target_os === 'Darwin') {
                $libcpp = '-lc++';
                $libs = str_replace($libcpp, '', $libs) . " {$libcpp}";
            } elseif ($target_os !== 'Windows') {
                // Linux and other Unix-like systems use libstdc++
                $libcpp = '-lstdc++';
                $libs = str_replace($libcpp, '', $libs) . " {$libcpp}";
            }
            // Windows (MSVC): C++ runtime is linked automatically, no explicit lib needed
        }

        if ($this->libs_only_deps) {
            // mimalloc must come first
            if (in_array('mimalloc', $resolved_package_names) && file_exists(BUILD_LIB_PATH . '/libmimalloc.a')) {
                $libs = BUILD_LIB_PATH . '/libmimalloc.a ' . str_replace([BUILD_LIB_PATH . '/libmimalloc.a', '-lmimalloc'], ['', ''], $libs);
            }
            return [
                'cflags' => $cflags,
                'cxxflags' => $cxxflags,
                'ldflags' => $ldflags,
                'libs' => clean_spaces(getenv('LIBS') . ' ' . $libs),
            ];
        }

        // embed
        if (!$this->no_php) {
            if (SystemTarget::getTargetOS() === 'Windows') {
                $major = intdiv(PHP_VERSION_ID, 10000);
                $php_lib = $this->absolute_libs ? BUILD_LIB_PATH . "\\php{$major}embed.lib" : "php{$major}embed.lib";
                $libs = "{$php_lib} {$libs} kernel32.lib ole32.lib user32.lib advapi32.lib shell32.lib ws2_32.lib dnsapi.lib psapi.lib bcrypt.lib";
            } else {
                $libs = "-lphp {$libs} -lc";
            }
        }

        $allLibs = getenv('LIBS') . ' ' . $libs;

        // mimalloc must come first
        if (in_array('mimalloc', $resolved_package_names) && file_exists(BUILD_LIB_PATH . '/libmimalloc.a')) {
            $allLibs = BUILD_LIB_PATH . '/libmimalloc.a ' . str_replace([BUILD_LIB_PATH . '/libmimalloc.a', '-lmimalloc'], ['', ''], $allLibs);
        }

        return [
            'cflags' => $cflags,
            'cxxflags' => $cxxflags,
            'ldflags' => $ldflags,
            'libs' => clean_spaces($allLibs),
        ];
    }

    /** @param string[] $package_names */
    public function getFrameworksString(array $package_names): string
    {
        $list = [];
        foreach ($package_names as $package_name) {
            foreach (PackageConfig::get($package_name, 'frameworks', []) as $fw) {
                $ks = '-framework ' . $fw;
                if (!in_array($ks, $list)) {
                    $list[] = $ks;
                }
            }
        }
        return implode(' ', $list);
    }

    /** @param string[] $package_names */
    private function hasCpp(array $package_names): bool
    {
        foreach ($package_names as $package_name) {
            $lang = PackageConfig::get($package_name, 'lang', 'c');
            if ($lang === 'cpp') {
                return true;
            }
        }
        return false;
    }

    /** @param string[] $package_names */
    private function getIncludesString(array $package_names): string
    {
        $base = BUILD_INCLUDE_PATH;

        // Windows MSVC uses /I flag instead of -I
        if (SystemTarget::getTargetOS() === 'Windows') {
            $includes = ["/I\"{$base}\""];

            // link with libphp
            if (!$this->no_php) {
                $includes = [
                    ...$includes,
                    "/I\"{$base}\\php\"",
                    "/I\"{$base}\\php\\main\"",
                    "/I\"{$base}\\php\\TSRM\"",
                    "/I\"{$base}\\php\\Zend\"",
                    "/I\"{$base}\\php\\ext\"",
                ];
            }
        } else {
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
        }

        // parse pkg-configs (only for Unix)
        if (SystemTarget::isUnix()) {
            foreach ($package_names as $package_name) {
                $pc = PackageConfig::get($package_name, 'pkg-configs', []);
                $pkg_config_path = getenv('PKG_CONFIG_PATH') ?: '';
                $search_paths = array_filter(explode(':', $pkg_config_path));
                foreach ($pc as $file) {
                    $found = false;
                    foreach ($search_paths as $path) {
                        if (file_exists($path . "/{$file}.pc")) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        throw new WrongUsageException("pkg-config file '{$file}.pc' for lib [{$package_name}] does not exist. Please build it first.");
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
        }
        $includes = array_unique($includes);
        return implode(' ', $includes);
    }

    private function getLdflagsString(): string
    {
        // Windows MSVC uses /LIBPATH flag instead of -L
        if (SystemTarget::getTargetOS() === 'Windows') {
            return '/LIBPATH:"' . BUILD_LIB_PATH . '"';
        }
        return '-L' . BUILD_LIB_PATH;
    }

    /** @param string[] $package_names */
    private function getLibsString(array $package_names, bool $use_short_libs = true): string
    {
        $lib_names = [];
        $frameworks = [];

        foreach ($package_names as $package_name) {
            // parse pkg-configs only for unix systems
            if (SystemTarget::isUnix()) {
                // add pkg-configs libs
                $pkg_configs = PackageConfig::get($package_name, 'pkg-configs', []);
                $pkg_config_path = getenv('PKG_CONFIG_PATH') ?: '';
                $search_paths = array_filter(explode(':', $pkg_config_path));
                foreach ($pkg_configs as $pkg_config) {
                    $found = false;
                    foreach ($search_paths as $path) {
                        if (file_exists($path . "/{$pkg_config}.pc")) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        throw new WrongUsageException("pkg-config file '{$pkg_config}.pc' for lib [{$package_name}] does not exist. Please build it first.");
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
            $libs = array_reverse(PackageConfig::get($package_name, 'static-libs', []));
            foreach ($libs as $lib) {
                if (FileSystem::isRelativePath($lib)) {
                    // check file existence
                    if (!file_exists(BUILD_LIB_PATH . "/{$lib}")) {
                        throw new WrongUsageException("Library file '{$lib}' for lib [{$package_name}] does not exist in '" . BUILD_LIB_PATH . "'. Please build it first.");
                    }
                    $lib_names[] = $this->getShortLibName($lib);
                } else {
                    $lib_names[] = $lib;
                }
            }
            // add frameworks for macOS
            if (SystemTarget::getTargetOS() === 'Darwin') {
                $frameworks = array_merge($frameworks, PackageConfig::get($package_name, 'frameworks', []));
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

        if (in_array('imap', $package_names) && SystemTarget::getTargetOS() === 'Linux' && SystemTarget::getLibc() === 'glibc') {
            if (file_exists(BUILD_LIB_PATH . '/libcrypt.a')) {
                $lib_names[] = '-lcrypt';
            }
        }
        if (!$use_short_libs) {
            $lib_names = array_map(fn ($l) => $this->getFullLibName($l), $lib_names);
        }
        return implode(' ', $lib_names);
    }

    private function getShortLibName(string $lib): string
    {
        // Windows: library files are xxx.lib format (not libxxx.a)
        if (SystemTarget::getTargetOS() === 'Windows') {
            if (!str_ends_with($lib, '.lib')) {
                return BUILD_LIB_PATH . '\\' . $lib;
            }
            // For Windows, return just the library filename (e.g., "libssl.lib")
            return $lib;
        }

        // Unix: library files are libxxx.a format
        if (!str_starts_with($lib, 'lib') || !str_ends_with($lib, '.a')) {
            return BUILD_LIB_PATH . '/' . $lib;
        }
        // get short name (e.g., "libssl.a" -> "-lssl")
        return '-l' . substr($lib, 3, -2);
    }

    private function getFullLibName(string $lib): string
    {
        // Windows: libraries don't use -l prefix, return as-is or with full path
        if (SystemTarget::getTargetOS() === 'Windows') {
            if (str_ends_with($lib, '.lib') && !str_contains($lib, '\\') && !str_contains($lib, '/')) {
                // It's a short lib name like "libssl.lib", convert to full path
                $fullPath = BUILD_LIB_PATH . '\\' . $lib;
                if (file_exists($fullPath)) {
                    return $fullPath;
                }
            }
            return $lib;
        }

        // Unix: convert -lxxx to full path
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
}
