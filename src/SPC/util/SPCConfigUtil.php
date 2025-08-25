<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\builder\BuilderBase;
use SPC\builder\BuilderProvider;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use Symfony\Component\Console\Input\ArgvInput;

class SPCConfigUtil
{
    private ?BuilderBase $builder = null;

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
    public function __construct(?BuilderBase $builder = null, array $options = [])
    {
        if ($builder !== null) {
            $this->builder = $builder; // BuilderProvider::makeBuilderByInput($input ?? new ArgvInput());
        }
        $this->no_php = $options['no_php'] ?? false;
        $this->libs_only_deps = $options['libs_only_deps'] ?? false;
        $this->absolute_libs = $options['absolute_libs'] ?? false;
    }

    /**
     * Generate configuration for building PHP extensions.
     *
     * @param array $extensions          Extension name list
     * @param array $libraries           Additional library name list
     * @param bool  $include_suggest_ext Include suggested extensions
     * @param bool  $include_suggest_lib Include suggested libraries
     * @return array{
     *     cflags: string,
     *     ldflags: string,
     *     libs: string
     * }
     */
    public function config(array $extensions = [], array $libraries = [], bool $include_suggest_ext = false, bool $include_suggest_lib = false): array
    {
        $extra_exts = [];
        foreach ($extensions as $ext) {
            $extra_exts = array_merge($extra_exts, Config::getExt($ext, 'ext-suggests', []));
        }
        foreach ($extra_exts as $ext) {
            if ($this->builder?->getExt($ext) && !in_array($ext, $extensions)) {
                $extensions[] = $ext;
            }
        }
        [$extensions, $libraries] = DependencyUtil::getExtsAndLibs($extensions, $libraries, $include_suggest_ext, $include_suggest_lib);

        ob_start();
        if ($this->builder === null) {
            $this->builder = BuilderProvider::makeBuilderByInput(new ArgvInput());
            $this->builder->proveLibs($libraries);
            $this->builder->proveExts($extensions, skip_extract: true);
        }
        ob_get_clean();
        $ldflags = $this->getLdflagsString();
        $cflags = $this->getIncludesString($libraries);
        $libs = $this->getLibsString($libraries, !$this->absolute_libs);

        // additional OS-specific libraries (e.g. macOS -lresolv)
        // embed
        if ($extra_libs = SPCTarget::getRuntimeLibs()) {
            $libs .= " {$extra_libs}";
        }
        $extra_env = getenv('SPC_EXTRA_LIBS');
        if (is_string($extra_env) && !empty($extra_env)) {
            $libs .= " {$extra_env}";
        }
        // extension frameworks
        if (SPCTarget::getTargetOS() === 'Darwin') {
            $libs .= " {$this->getFrameworksString($extensions)}";
        }
        if ($this->builder->hasCpp()) {
            $libcpp = SPCTarget::getTargetOS() === 'Darwin' ? '-lc++' : '-lstdc++';
            $libs = str_replace($libcpp, '', $libs) . " {$libcpp}";
        }

        if ($this->libs_only_deps) {
            // mimalloc must come first
            if ($this->builder->getLib('mimalloc') && file_exists(BUILD_LIB_PATH . '/libmimalloc.a')) {
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
        if ($this->builder->getLib('mimalloc') && file_exists(BUILD_LIB_PATH . '/libmimalloc.a')) {
            $allLibs = BUILD_LIB_PATH . '/libmimalloc.a ' . str_replace([BUILD_LIB_PATH . '/libmimalloc.a', '-lmimalloc'], ['', ''], $allLibs);
        }

        return [
            'cflags' => clean_spaces(getenv('CFLAGS') . ' ' . $cflags),
            'ldflags' => clean_spaces(getenv('LDFLAGS') . ' ' . $ldflags),
            'libs' => clean_spaces($allLibs),
        ];
    }

    private function getIncludesString(array $libraries): string
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
        foreach ($libraries as $library) {
            $pc = Config::getLib($library, 'pkg-configs', []);
            foreach ($pc as $file) {
                if (!file_exists(BUILD_LIB_PATH . "/pkgconfig/{$file}.pc")) {
                    throw new WrongUsageException("pkg-config file '{$file}.pc' for lib [{$library}] does not exist in '" . BUILD_LIB_PATH . "/pkgconfig'. Please build it first.");
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

    private function getLibsString(array $libraries, bool $use_short_libs = true): string
    {
        $lib_names = [];
        $frameworks = [];

        foreach ($libraries as $library) {
            // add pkg-configs libs
            $pkg_configs = Config::getLib($library, 'pkg-configs', []);
            foreach ($pkg_configs as $pkg_config) {
                if (!file_exists(BUILD_LIB_PATH . "/pkgconfig/{$pkg_config}.pc")) {
                    throw new WrongUsageException("pkg-config file '{$pkg_config}.pc' for lib [{$library}] does not exist in '" . BUILD_LIB_PATH . "/pkgconfig'. Please build it first.");
                }
            }
            $pkg_configs = implode(' ', $pkg_configs);
            if ($pkg_configs !== '') {
                // static libs with dependencies come in reverse order, so reverse this too
                $pc_libs = array_reverse(PkgConfigUtil::getLibsArray($pkg_configs));
                $lib_names = [...$lib_names, ...$pc_libs];
            }
            // convert all static-libs to short names
            $libs = array_reverse(Config::getLib($library, 'static-libs', []));
            foreach ($libs as $lib) {
                // check file existence
                if (!file_exists(BUILD_LIB_PATH . "/{$lib}")) {
                    throw new WrongUsageException("Library file '{$lib}' for lib [{$library}] does not exist in '" . BUILD_LIB_PATH . "'. Please build it first.");
                }
                $lib_names[] = $this->getShortLibName($lib);
            }
            // add frameworks for macOS
            if (SPCTarget::getTargetOS() === 'Darwin') {
                $frameworks = array_merge($frameworks, Config::getLib($library, 'frameworks', []));
            }
        }

        // post-process
        $lib_names = array_filter($lib_names, fn ($x) => $x !== '');
        $lib_names = array_reverse(array_unique($lib_names));
        $frameworks = array_unique($frameworks);

        // process frameworks to short_name
        if (SPCTarget::getTargetOS() === 'Darwin') {
            foreach ($frameworks as $fw) {
                $ks = '-framework ' . $fw;
                if (!in_array($ks, $lib_names)) {
                    $lib_names[] = $ks;
                }
            }
        }

        if (in_array('imap', $libraries) && SPCTarget::getLibc() === 'glibc') {
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

    private function getFullLibName(string $lib)
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
            foreach (Config::getExt($extension, 'frameworks', []) as $fw) {
                $ks = '-framework ' . $fw;
                if (!in_array($ks, $list)) {
                    $list[] = $ks;
                }
            }
        }
        return implode(' ', $list);
    }
}
