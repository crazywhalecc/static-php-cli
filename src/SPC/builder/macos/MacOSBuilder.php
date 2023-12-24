<?php

declare(strict_types=1);

namespace SPC\builder\macos;

use SPC\builder\BuilderBase;
use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\builder\traits\UnixBuilderTrait;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
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
        f_putenv('CC=' . $this->getOption('cc', 'clang'));
        // set C++ Composer (default: clang++)
        f_putenv('CXX=' . $this->getOption('cxx', 'clang++'));
        // set PATH
        f_putenv('PATH=' . BUILD_ROOT_PATH . '/bin:' . getenv('PATH'));
        // set PKG_CONFIG
        f_putenv('PKG_CONFIG=' . BUILD_ROOT_PATH . '/bin/pkg-config');
        // set PKG_CONFIG_PATH
        f_putenv('PKG_CONFIG_PATH=' . BUILD_LIB_PATH . '/pkgconfig/');

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
        $extra_libs .= (empty($extra_libs) ? '' : ' ') . ($this->hasCpp() ? '-lc++ ' : '');
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
        $zts = $this->getOption('enable-zts', false) ? '--enable-zts --disable-zend-signals ' : '';

        $enableCli = ($build_target & BUILD_TARGET_CLI) === BUILD_TARGET_CLI;
        $enableFpm = ($build_target & BUILD_TARGET_FPM) === BUILD_TARGET_FPM;
        $enableMicro = ($build_target & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO;
        $enableEmbed = ($build_target & BUILD_TARGET_EMBED) === BUILD_TARGET_EMBED;

        f_putenv('PKG_CONFIG=' . BUILD_ROOT_PATH . '/bin/pkg-config');
        f_putenv('PKG_CONFIG_PATH=' . BUILD_LIB_PATH . '/pkgconfig');

        $x_cppflags = '';
        $x_ldflags = '';
        $x_libs = '';
        $x_extra_cflags = '';
        $x_extra_libs = '';
        if ($this->getExt('swoole')) {
            $packages = 'openssl libssl libnghttp2 libcares libbrotlicommon libbrotlidec libbrotlienc zlib libcurl ';
            if ($this->getLib('postgresql')) {
                $packages .= ' libpq ';
            }

            $output = shell()->execWithResult("pkg-config --cflags-only-I --static {$packages}");
            if (!empty($output[1][0])) {
                $x_cppflags = $output[1][0];
            }
            $output = shell()->execWithResult("pkg-config --libs-only-L --static {$packages}");
            if (!empty($output[1][0])) {
                $x_ldflags = $output[1][0];
            }
            $output = shell()->execWithResult("pkg-config --libs-only-l --static {$packages}");
            if (!empty($output[1][0])) {
                $x_libs = $output[1][0];
            }
            $x_libs = $x_libs . ' -lm -lc++ ';
            $output = shell()->execWithResult("pkg-config --cflags --static {$packages}");
            if (!empty($output[1][0])) {
                $x_extra_cflags = $output[1][0];
            }
            $output = shell()->execWithResult("pkg-config --libs --static {$packages}");
            if (!empty($output[1][0])) {
                $x_extra_libs = $output[1][0];
            }
            $x_extra_libs .= ' ' . $x_libs;
            $x_extra_cflags .= ' -I' . SOURCE_PATH . '/php-src/ext/ ';

            logger()->info('CPPFLAGS INFO: ' . $x_cppflags);
            logger()->info('LDFLAGS INFO: ' . $x_ldflags);
            logger()->info('LIBS INFO: ' . $x_libs);
            logger()->info('EXTRA_CFLAGS INFO: ' . $x_extra_cflags);
            logger()->info('EXTRA_LIBS INFO: ' . $x_extra_libs);
        }
        $this->setOption('x-extra-cflags', $x_extra_cflags);
        $this->setOption('x-extra-libs', $x_extra_libs);

        // prepare build php envs
        $envs_build_php = SystemUtil::makeEnvVarString([
            'CPPFLAGS' => '-I' . BUILD_INCLUDE_PATH . ' ' . $x_cppflags,
            'LDFLAGS' => '-L' . BUILD_LIB_PATH . ' ' . $x_ldflags,
            'LIBS' => $x_libs,
        ]);

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec(
                './configure ' .
                '--prefix= ' .
                '--with-valgrind=no ' .     // Not detect memory leak
                '--enable-shared=no ' .
                '--enable-static=yes ' .
                '--disable-all ' .
                '--disable-cgi ' .
                '--disable-phpdbg ' .
                ($enableCli ? '--enable-cli ' : '--disable-cli ') .
                ($enableFpm ? '--enable-fpm ' : '--disable-fpm ') .
                ($enableEmbed ? '--enable-embed=static ' : '--disable-embed ') .
                ($enableMicro ? '--enable-micro ' : '--disable-micro ') .
                $json_74 .
                $zts .
                $this->makeExtensionArgs() .
                ' ' . $envs_build_php . ' '
            );

        SourcePatcher::patchBeforeMake($this);

        $this->cleanMake();

        if ($enableCli) {
            logger()->info('building cli');
            $this->buildCli();
        }
        if ($enableFpm) {
            logger()->info('building fpm');
            $this->buildFpm();
        }
        if ($enableMicro) {
            logger()->info('building micro');
            $this->buildMicro();
        }
        if ($enableEmbed) {
            logger()->info('building embed');
            if ($enableMicro) {
                FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/Makefile', 'OVERALL_TARGET =', 'OVERALL_TARGET = libphp.la');
            }
            $this->buildEmbed();
        }

        if (php_uname('m') === $this->getOption('arch')) {
            $this->sanityCheck($build_target);
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
            'EXTRA_LIBS' => "{$this->getOption('extra-libs')} -lresolv", // link resolv library (macOS needs it)
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
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function buildMicro(): void
    {
        if ($this->getPHPVersionID() < 80000) {
            throw new WrongUsageException('phpmicro only support PHP >= 8.0!');
        }
        if ($this->getExt('phar')) {
            $this->phar_patched = true;
            SourcePatcher::patchMicro(['phar']);
        }

        $enable_fake_cli = $this->getOption('with-micro-fake-cli', false) ? ' -DPHP_MICRO_FAKE_CLI' : '';
        $vars = [
            // with debug information, optimize for size, remove identifiers, patch fake cli for micro
            'EXTRA_CFLAGS' => '-g -Os -fno-ident' . $enable_fake_cli,
            // link resolv library (macOS needs it)
            'EXTRA_LIBS' => "{$this->getOption('extra-libs')} -lresolv",
        ];
        if (!$this->getOption('no-strip', false)) {
            $vars['STRIP'] = 'dsymutil -f ';
        }
        $vars = SystemUtil::makeEnvVarString($vars);

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec("make -j{$this->concurrency} {$vars} micro");
        $this->deployBinary(BUILD_TARGET_MICRO);

        if ($this->phar_patched) {
            SourcePatcher::patchMicro(['phar'], true);
        }
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
            'EXTRA_LIBS' => "{$this->getOption('extra-libs')} -lresolv", // link resolv library (macOS needs it)
        ]);

        $shell = shell()->cd(SOURCE_PATH . '/php-src');
        $shell->exec("make -j{$this->concurrency} {$vars} fpm");
        if (!$this->getOption('no-strip', false)) {
            $shell->exec('dsymutil -f sapi/fpm/php-fpm')->exec('strip sapi/fpm/php-fpm');
        }
        $this->deployBinary(BUILD_TARGET_FPM);
    }

    /**
     * Build embed sapi
     *
     * @throws RuntimeException
     */
    public function buildEmbed(): void
    {
        $vars = SystemUtil::makeEnvVarString([
            'EXTRA_CFLAGS' => '-g -Os', // with debug information, but optimize for size
            'EXTRA_LIBS' => "{$this->getOption('extra-libs')} -lresolv", // link resolv library (macOS needs it)
        ]);

        shell()
            ->cd(SOURCE_PATH . '/php-src')
            ->exec('make INSTALL_ROOT=' . BUILD_ROOT_PATH . " -j{$this->concurrency} {$vars} install")
            // Workaround for https://github.com/php/php-src/issues/12082
            ->exec('rm -Rf ' . BUILD_ROOT_PATH . '/lib/php-o')
            ->exec('mkdir ' . BUILD_ROOT_PATH . '/lib/php-o')
            ->cd(BUILD_ROOT_PATH . '/lib/php-o')
            ->exec('ar x ' . BUILD_ROOT_PATH . '/lib/libphp.a')
            ->exec('rm ' . BUILD_ROOT_PATH . '/lib/libphp.a')
            ->exec('ar rcs ' . BUILD_ROOT_PATH . '/lib/libphp.a *.o')
            ->exec('rm -Rf ' . BUILD_ROOT_PATH . '/lib/php-o');
    }
}
