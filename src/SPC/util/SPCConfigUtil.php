<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\builder\BuilderBase;
use SPC\builder\BuilderProvider;
use SPC\builder\macos\MacOSBuilder;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
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
     * @throws \ReflectionException
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     * @throws \Throwable
     */
    public function config(array $extensions = [], array $libraries = [], bool $include_suggest_ext = false, bool $include_suggest_lib = false, bool $with_dependencies = false): array
    {
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
        $extra_env = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LIBS');
        if (is_string($extra_env)) {
            $libs .= ' ' . trim($extra_env, '"');
        }
        $extra_env = getenv('SPC_EXTRA_LIBS');
        if (is_string($extra_env) && !empty($extra_env)) {
            $libs .= " {$extra_env}";
        }
        // extension frameworks
        if (SPCTarget::getTargetOS() === 'Darwin') {
            $libs .= " {$this->getFrameworksString($extensions)}";
        }
        $libs .= $this->builder->hasCpp() && $this->builder instanceof MacOSBuilder ? ' -lc++' : ' -lstdc++';

        if ($this->libs_only_deps) {
            return [
                'cflags' => trim(getenv('CFLAGS') . ' ' . $cflags),
                'ldflags' => trim(getenv('LDFLAGS') . ' ' . $ldflags),
                'libs' => trim(getenv('LIBS') . ' ' . $libs),
            ];
        }

        // embed
        if (!$this->no_php) {
            $libs = "-lphp -lc {$libs}";
        }
        // mimalloc must come first
        if (str_contains($libs, BUILD_LIB_PATH . '/mimalloc.o')) {
            $libs = BUILD_LIB_PATH . '/mimalloc.o ' . str_replace(BUILD_LIB_PATH . '/mimalloc.o', '', $libs);
        }
        return [
            'cflags' => trim(getenv('CFLAGS') . ' ' . $cflags),
            'ldflags' => trim(getenv('LDFLAGS') . ' ' . $ldflags),
            'libs' => trim(getenv('LIBS') . ' ' . $libs),
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
            $pc_cflags = implode(' ', Config::getLib($library, 'pkg-configs', []));
            if ($pc_cflags !== '') {
                $pc_cflags = PkgConfigUtil::getCflags($pc_cflags);
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
            // convert all static-libs to short names
            $libs = Config::getLib($library, 'static-libs', []);
            foreach ($libs as $lib) {
                // check file existence
                if (!file_exists(BUILD_LIB_PATH . "/{$lib}")) {
                    throw new WrongUsageException("Library file '{$lib}' for lib [{$library}] does not exist in '" . BUILD_LIB_PATH . "'. Please build it first.");
                }
                $lib_names[] = $use_short_libs ? $this->getShortLibName($lib) : (BUILD_LIB_PATH . "/{$lib}");
            }
            // add frameworks for macOS
            if (SPCTarget::getTargetOS() === 'Darwin') {
                $frameworks = array_merge($frameworks, Config::getLib($library, 'frameworks', []));
            }
            // add pkg-configs libs
            $pkg_configs = Config::getLib($library, 'pkg-configs', []);
            foreach ($pkg_configs as $pkg_config) {
                if (!file_exists(BUILD_LIB_PATH . "/pkgconfig/{$pkg_config}.pc")) {
                    throw new WrongUsageException("pkg-config file '{$pkg_config}.pc' for lib [{$library}] does not exist in '" . BUILD_LIB_PATH . "/pkgconfig'. Please build it first.");
                }
            }
            $pkg_configs = implode(' ', $pkg_configs);
            if ($pkg_configs !== '') {
                $pc_libs = array_reverse(PkgConfigUtil::getLibsArray($pkg_configs, $use_short_libs));
                $lib_names = [...$lib_names, ...$pc_libs];
            }
        }

        // post-process
        $lib_names = array_unique(array_reverse($lib_names));
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
