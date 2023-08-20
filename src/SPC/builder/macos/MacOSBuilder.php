<?php

declare(strict_types=1);

namespace SPC\builder\macos;

use SPC\builder\BuilderBase;
use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\builder\traits\UnixBuilderTrait;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\SourcePatcher;

class MacOSBuilder extends BuilderBase
{
    /** Unix compatible builder methods */
    use UnixBuilderTrait;

    /** @var bool Micro patch phar flag */
    private bool $phar_patched = false;

    /**
     * @throws RuntimeException
     * @throws WrongUsageException
     * @throws FileSystemException
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;

        // ---------- set necessary options ----------
        // set C Compiler (default: clang)
        $this->setOptionIfNotExist('cc', 'clang');
        // set C++ Composer (default: clang++)
        $this->setOptionIfNotExist('cxx', 'clang++');
        // set arch (default: current)
        $this->setOptionIfNotExist('arch', php_uname('m'));
        $this->setOptionIfNotExist('gnu-arch', arch2gnu($this->getOption('arch')));

        // ---------- set necessary compile environments ----------
        // concurrency
        $this->concurrency = SystemUtil::getCpuCount();
        // cflags
        $this->arch_c_flags = SystemUtil::getArchCFlags($this->getOption('arch'));
        $this->arch_cxx_flags = SystemUtil::getArchCFlags($this->getOption('arch'));
        // cmake toolchain
        $this->cmake_toolchain_file = SystemUtil::makeCmakeToolchainFile('Darwin', $this->getOption('arch'), $this->arch_c_flags);
        // configure environment
        $this->configure_env = SystemUtil::makeEnvVarString([
            'PKG_CONFIG' => BUILD_ROOT_PATH . '/bin/pkg-config',
            'PKG_CONFIG_PATH' => BUILD_LIB_PATH . '/pkgconfig/',
            'CC' => $this->getOption('cc'),
            'CXX' => $this->getOption('cxx'),
            'CFLAGS' => "{$this->arch_c_flags} -Wimplicit-function-declaration -Os",
        ]);

        // create pkgconfig and include dir (some libs cannot create them automatically)
        f_mkdir(BUILD_LIB_PATH . '/pkgconfig', recursive: true);
        f_mkdir(BUILD_INCLUDE_PATH, recursive: true);
    }

    /**
     * [deprecated] 生成库构建采用的 autoconf 参数列表
     *
     * @param string $name      要构建的 lib 库名，传入仅供输出日志
     * @param array  $lib_specs 依赖的 lib 库的 autoconf 文件
     */
    public function makeAutoconfArgs(string $name, array $lib_specs): string
    {
        $ret = '';
        foreach ($lib_specs as $libName => $arr) {
            $lib = $this->getLib($libName);

            $arr = $arr ?? [];

            $disableArgs = $arr[0] ?? null;
            if ($lib instanceof MacOSLibraryBase) {
                logger()->info("{$name} \033[32;1mwith\033[0;1m {$libName} support");
                $ret .= '--with-' . $libName . '=yes ';
            } else {
                logger()->info("{$name} \033[31;1mwithout\033[0;1m {$libName} support");
                $ret .= ($disableArgs ?? "--with-{$libName}=no") . ' ';
            }
        }
        return rtrim($ret);
    }

    /**
     * Get dynamically linked macOS frameworks
     *
     * @param  bool                $asString If true, return as string
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    public function getFrameworks(bool $asString = false): array|string
    {
        $libs = [];

        // reorder libs
        foreach ($this->libs as $lib) {
            foreach ($lib->getDependencies() as $dep) {
                $libs[] = $dep;
            }
            $libs[] = $lib;
        }

        $frameworks = [];
        /** @var MacOSLibraryBase $lib */
        foreach ($libs as $lib) {
            array_push($frameworks, ...$lib->getFrameworks());
        }

        if ($asString) {
            return implode(' ', array_map(fn ($x) => "-framework {$x}", $frameworks));
        }
        return $frameworks;
    }

    /**
     * Just start to build statically linked php binary
     *
     * @param  int                 $build_target build target
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function buildPHP(int $build_target = BUILD_TARGET_NONE): void
    {
        // ---------- Update extra-libs ----------
        $extra_libs = $this->getOption('extra-libs', '');
        // add macOS frameworks
        $extra_libs .= (empty($extra_libs) ? '' : ' ') . $this->getFrameworks(true);
        // add libc++, some extensions or libraries need it (C++ cannot be linked statically)
        $extra_libs .= (empty($extra_libs) ? '' : ' ') . ($this->hasCppExtension() ? '-lc++ ' : '');
        if (!$this->getOption('bloat', false)) {
            $extra_libs .= (empty($extra_libs) ? '' : ' ') . implode(' ', $this->getAllStaticLibFiles());
        } else {
            logger()->info('bloat linking');
            $extra_libs .= (empty($extra_libs) ? '' : ' ') . implode(' ', array_map(fn ($x) => "-Wl,-force_load,{$x}", array_filter($this->getAllStaticLibFiles())));
        }
        $this->setOption('extra-libs', $extra_libs);

        SourcePatcher::patchBeforeBuildconf($this);

        shell()->cd(SOURCE_PATH . '/php-src')->exec('./buildconf --force');

        SourcePatcher::patchBeforeConfigure($this);

        $json_74 = $this->getPHPVersionID() < 80000 ? '--enable-json ' : '';
        $zts = $this->getOption('enable-zts', false) ? '--enable-zts ' : '';

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec(
                './configure ' .
                '--prefix= ' .
                '--with-valgrind=no ' .     // Not detect memory leak
                '--enable-shared=no ' .
                '--enable-static=yes ' .
                "CFLAGS='{$this->arch_c_flags} -Werror=unknown-warning-option' " .
                '--disable-all ' .
                '--disable-cgi ' .
                '--disable-phpdbg ' .
                '--enable-cli ' .
                '--enable-fpm ' .
                '--enable-micro ' .
                $json_74 .
                $zts .
                $this->makeExtensionArgs() . ' ' .
                $this->configure_env
            );

        SourcePatcher::patchBeforeMake($this);

        $this->cleanMake();

        if (($build_target & BUILD_TARGET_CLI) === BUILD_TARGET_CLI) {
            logger()->info('building cli');
            $this->buildCli();
        }
        if (($build_target & BUILD_TARGET_FPM) === BUILD_TARGET_FPM) {
            logger()->info('building fpm');
            $this->buildFpm();
        }
        if (($build_target & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO) {
            logger()->info('building micro');
            $this->buildMicro();
        }

        if (php_uname('m') === $this->getOption('arch')) {
            $this->sanityCheck($build_target);
        }

        if ($this->phar_patched) {
            SourcePatcher::patchMicro(['phar'], true);
        }
    }

    /**
     * Build cli sapi
     *
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function buildCli(): void
    {
        $vars = SystemUtil::makeEnvVarString([
            'EXTRA_CFLAGS' => '-g -Os', // with debug information, but optimize for size
            'EXTRA_LIBS' => "{$this->getOption('extra-libs')} -lresolv", // link resolv library (macOS need it)
        ]);

        $shell = shell()->cd(SOURCE_PATH . '/php-src');
        $shell->exec("make -j{$this->concurrency} {$vars} cli");
        if (!$this->getOption('no-strip', false)) {
            $shell->exec('dsymutil -f sapi/cli/php')->exec('strip sapi/cli/php');
        }
        $this->deployBinary(BUILD_TARGET_CLI);
    }

    /**
     * Build phpmicro sapi
     *
     * @throws FileSystemException|RuntimeException
     */
    public function buildMicro(): void
    {
        if ($this->getPHPVersionID() < 80000) {
            throw new RuntimeException('phpmicro only support PHP >= 8.0!');
        }
        if ($this->getExt('phar')) {
            $this->phar_patched = true;
            SourcePatcher::patchMicro(['phar']);
        }

        $enable_fake_cli = $this->getOption('with-micro-fake-cli', false) ? ' -DPHP_MICRO_FAKE_CLI' : '';
        $vars = [
            // with debug information, optimize for size, remove identifiers, patch fake cli for micro
            'EXTRA_CFLAGS' => '-g -Os -fno-ident' . $enable_fake_cli,
            // link resolv library (macOS need it)
            'EXTRA_LIBS' => "{$this->getOption('extra-libs')} -lresolv",
        ];
        if (!$this->getOption('no-strip', false)) {
            $vars['STRIP'] = 'dsymutil -f ';
        }
        $vars = SystemUtil::makeEnvVarString($vars);

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec("make -j{$this->concurrency} {$vars} micro");
        $this->deployBinary(BUILD_TARGET_MICRO);
    }

    /**
     * Build fpm sapi
     *
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function buildFpm(): void
    {
        $vars = SystemUtil::makeEnvVarString([
            'EXTRA_CFLAGS' => '-g -Os', // with debug information, but optimize for size
            'EXTRA_LIBS' => "{$this->getOption('extra-libs')} -lresolv", // link resolv library (macOS need it)
        ]);

        $shell = shell()->cd(SOURCE_PATH . '/php-src');
        $shell->exec("make -j{$this->concurrency} {$vars} fpm");
        if (!$this->getOption('no-strip', false)) {
            $shell->exec('dsymutil -f sapi/fpm/php-fpm')->exec('strip sapi/fpm/php-fpm');
        }
        $this->deployBinary(BUILD_TARGET_FPM);
    }
}
