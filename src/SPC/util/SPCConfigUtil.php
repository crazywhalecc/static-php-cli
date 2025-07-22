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

    public function __construct(?BuilderBase $builder = null, private bool $link_php = true)
    {
        if ($builder !== null) {
            $this->builder = $builder; // BuilderProvider::makeBuilderByInput($input ?? new ArgvInput());
        }
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
        $libs = $this->getLibsString($libraries);
        if (SPCTarget::getTargetOS() === 'Darwin') {
            $libs .= " {$this->getFrameworksString($extensions)}";
        }
        $cflags = $this->getIncludesString($libraries);

        $libs = trim("-lc {$libs}");
        // embed
        if ($this->link_php) {
            $libs = "-lphp {$libs}";
        }
        $extra_env = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LIBS');
        if (is_string($extra_env)) {
            $libs .= ' ' . trim($extra_env, '"');
        }
        // c++
        if ($this->builder->hasCpp()) {
            $libs .= $this->builder instanceof MacOSBuilder ? ' -lc++' : ' -lstdc++';
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
        if ($this->link_php) {
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

    private function getLibsString(array $libraries): string
    {
        $short_name = [];
        $frameworks = [];

        foreach ($libraries as $library) {
            // convert all static-libs to short names
            $libs = Config::getLib($library, 'static-libs', []);
            foreach ($libs as $lib) {
                // check file existence
                if (!file_exists(BUILD_LIB_PATH . "/{$lib}")) {
                    throw new WrongUsageException("Library file '{$lib}' for lib [{$library}] does not exist in '" . BUILD_LIB_PATH . "'. Please build it first.");
                }
                $short_name[] = $this->getShortLibName($lib);
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
                $pc_libs = PkgConfigUtil::getLibsArray($pkg_configs);
                $short_name = [...$short_name, ...$pc_libs];
            }
        }

        // post-process
        $short_name = array_unique(array_reverse($short_name));
        $frameworks = array_unique(array_reverse($frameworks));

        // process frameworks to short_name
        if (SPCTarget::getTargetOS() === 'Darwin') {
            foreach ($frameworks as $fw) {
                $ks = '-framework ' . $fw;
                if (!in_array($ks, $short_name)) {
                    $short_name[] = $ks;
                }
            }
        }

        if (in_array('imap', $libraries) && SPCTarget::getLibc() === 'glibc') {
            $short_name[] = '-lcrypt';
        }
        return implode(' ', $short_name);
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
