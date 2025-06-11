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

    public function __construct(?BuilderBase $builder = null)
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
        $libs = $this->getLibsString($libraries, $with_dependencies);
        if (PHP_OS_FAMILY === 'Darwin') {
            $libs .= " {$this->getFrameworksString($extensions)}";
        }
        $cflags = $this->getIncludesString();

        // embed
        $libs = trim("-lphp -lc {$libs}");
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

    private function getIncludesString(): string
    {
        $base = BUILD_INCLUDE_PATH;
        $php_embed_includes = [
            "-I{$base}",
            "-I{$base}/php",
            "-I{$base}/php/main",
            "-I{$base}/php/TSRM",
            "-I{$base}/php/Zend",
            "-I{$base}/php/ext",
        ];
        return implode(' ', $php_embed_includes);
    }

    private function getLdflagsString(): string
    {
        return '-L' . BUILD_LIB_PATH;
    }

    private function getLibsString(array $libraries, bool $withDependencies = false): string
    {
        $short_name = [];
        foreach (array_reverse($libraries) as $library) {
            $libs = Config::getLib($library, 'static-libs', []);
            foreach ($libs as $lib) {
                if ($withDependencies) {
                    $noExt = str_replace('.a', '', $lib);
                    $requiredLibs = [];
                    $pkgconfFile = BUILD_LIB_PATH . "/pkgconfig/{$noExt}.pc";
                    if (file_exists($pkgconfFile)) {
                        $lines = file($pkgconfFile);
                        foreach ($lines as $value) {
                            if (str_starts_with($value, 'Libs')) {
                                $items = explode(' ', $value);
                                foreach ($items as $item) {
                                    $item = trim($item);
                                    if (str_starts_with($item, '-l')) {
                                        $requiredLibs[] = $item;
                                    }
                                }
                            }
                        }
                    } else {
                        $requiredLibs[] = $this->getShortLibName($lib);
                    }
                    foreach ($requiredLibs as $requiredLib) {
                        if (!in_array($requiredLib, $short_name)) {
                            $short_name[] = $requiredLib;
                        }
                    }
                } else {
                    $short_name[] = $this->getShortLibName($lib);
                }
            }
            if (PHP_OS_FAMILY !== 'Darwin') {
                continue;
            }
            foreach (Config::getLib($library, 'frameworks', []) as $fw) {
                $ks = '-framework ' . $fw;
                if (!in_array($ks, $short_name)) {
                    $short_name[] = $ks;
                }
            }
        }
        // patch: imagick (imagemagick wrapper) for linux needs libgomp
        if (in_array('imagemagick', $libraries) && PHP_OS_FAMILY === 'Linux' && !(getenv('SPC_LIBC') === 'glibc' && str_contains(getenv('CC'), 'devtoolset-10'))) {
            $short_name[] = '-lgomp';
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
