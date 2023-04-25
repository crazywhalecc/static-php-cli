<?php

declare(strict_types=1);

namespace SPC\builder\linux;

use SPC\builder\BuilderBase;
use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\traits\UnixBuilderTrait;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\FileSystem;
use SPC\util\Patcher;

/**
 * Linux 系统环境下的构建器
 */
class LinuxBuilder extends BuilderBase
{
    /** 编译的 Unix 工具集 */
    use UnixBuilderTrait;

    /** @var string[] Linux 环境下编译依赖的命令 */
    public const REQUIRED_COMMANDS = [
        'make',
        'bison',
        'flex',
        'pkg-config',
        'git',
        'autoconf',
        'automake',
        'tar',
        'unzip',
        /* 'xz', 好像不需要 */
        'gzip',
        'bzip2',
        'cmake',
    ];

    /** @var string 使用的 libc */
    public string $libc;

    /** @var array 特殊架构下的 cflags */
    public array $tune_c_flags;

    /** @var string pkg-config 环境变量 */
    public string $pkgconf_env;

    /** @var string 交叉编译变量 */
    public string $cross_compile_prefix = '';

    public string $note_section = "Je pense, donc je suis\0";

    private bool $phar_patched = false;

    /**
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function __construct(?string $cc = null, ?string $cxx = null, ?string $arch = null)
    {
        // 初始化一些默认参数
        $this->cc = $cc ?? 'musl-gcc';
        $this->cxx = $cxx ?? 'g++';
        $this->arch = $arch ?? php_uname('m');
        $this->gnu_arch = arch2gnu($this->arch);
        $this->libc = match ($this->cc) {
            'gcc' => 'glibc',
            'musl-gcc', 'clang' => 'libc',
            default => ''
        }; // SystemUtil::selectLibc($this->cc);

        $this->ld = match ($this->cc) {
            'musl-gcc', 'gcc' => 'ld',
            'clang' => 'ld.lld',
            default => throw new RuntimeException('no found ld'),
        };

        // 根据 CPU 线程数设置编译进程数
        $this->concurrency = SystemUtil::getCpuCount();
        // 设置 cflags
        $this->arch_c_flags = SystemUtil::getArchCFlags($this->cc, $this->arch);
        $this->arch_cxx_flags = SystemUtil::getArchCFlags($this->cxx, $this->arch);
        $this->tune_c_flags = SystemUtil::checkCCFlags(SystemUtil::getTuneCFlags($this->arch), $this->cc);
        // 设置 cmake
        $this->cmake_toolchain_file = SystemUtil::makeCmakeToolchainFile(
            os: 'Linux',
            target_arch: $this->arch,
            cflags: $this->arch_c_flags,
            cc: $this->cc,
            cxx: $this->cxx
        );
        $build_root_path = BUILD_ROOT_PATH;
        $build_lib_path = BUILD_LIB_PATH;
        // 设置 pkgconfig
        $this->pkgconf_env = 'export PKG_CONFIG_PATH="' . $build_lib_path . '/pkgconfig:/usr/lib/pkgconfig"';

        // 设置 configure 依赖的环境变量
        $this->configure_env = <<<EOF
        set -uex
        export CC={$this->cc}
        export CXX={$this->cxx}
        export LD={$this->ld}
        export PATH={$build_root_path}/bin/:\$PATH
        {$this->pkgconf_env}
EOF;
        $this->configure_env = PHP_EOL . $this->configure_env . PHP_EOL;

        php_uname('m') === $this->arch ? '' : "CFLAGS='{$this->arch_c_flags}'";
        // 交叉编译依赖的，TODO
        if (php_uname('m') !== $this->arch) {
            $this->cross_compile_prefix = SystemUtil::getCrossCompilePrefix($this->cc, $this->arch);
            logger()->info('using cross compile prefix: ' . $this->cross_compile_prefix);
            $this->configure_env .= " CROSS_COMPILE='{$this->cross_compile_prefix}'";
        }

        $missing = [];
        foreach (self::REQUIRED_COMMANDS as $cmd) {
            if (SystemUtil::findCommand($cmd) === null) {
                $missing[] = $cmd;
            }
        }
        if (!empty($missing)) {
            throw new WrongUsageException('missing system commands: ' . implode(', ', $missing));
        }

        // 创立 pkg-config 和放头文件的目录
        f_mkdir(BUILD_ROOT_PATH . '/bin', recursive: true);
        f_mkdir(BUILD_LIB_PATH . '/pkgconfig', recursive: true);
        f_mkdir(BUILD_INCLUDE_PATH, recursive: true);
    }

    public function makeAutoconfArgs(string $name, array $libSpecs): string
    {
        $ret = '';
        foreach ($libSpecs as $libName => $arr) {
            $lib = $this->getLib($libName);

            $arr = $arr ?? [];

            $disableArgs = $arr[0] ?? null;
            $prefix = $arr[1] ?? null;
            if ($lib instanceof LinuxLibraryBase) {
                logger()->info("{$name} \033[32;1mwith\033[0;1m {$libName} support");
                $ret .= $lib->makeAutoconfEnv($prefix) . ' ';
            } else {
                logger()->info("{$name} \033[31;1mwithout\033[0;1m {$libName} support");
                $ret .= ($disableArgs ?? "--with-{$libName}=no") . ' ';
            }
        }
        return rtrim($ret);
    }

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function buildPHP(int $build_target = BUILD_TARGET_NONE, bool $with_clean = false, bool $bloat = false)
    {
        if (!$bloat) {
            $extra_libs = implode(' ', $this->getAllStaticLibFiles());
        } else {
            logger()->info('bloat linking');
            $extra_libs = implode(
                ' ',
                array_map(
                    fn ($x) => "-Xcompiler {$x}",
                    array_filter($this->getAllStaticLibFiles())
                )
            );
        }

        $envs = '';
        $cflags = $this->arch_c_flags;
        $use_lld = '';

        switch ($this->libc) {
            case 'musl_wrapper':
            case 'glibc':
                $cflags .= ' -static-libgcc -I"' . BUILD_INCLUDE_PATH . '"';
                break;
            case 'musl':
            case 'libc':
                if (str_ends_with($this->cc, 'clang') && SystemUtil::findCommand('lld')) {
                    $use_lld = '-Xcompiler -fuse-ld=lld'; // lld = ld.lld  soft link
                }
                break;
            default:
                throw new WrongUsageException('libc ' . $this->libc . ' is not implemented yet  ' . __FILE__ . ':' . __LINE__);
        }

        $lib_meta = FileSystem::loadConfigArray('lib');
        $packages = [];
        $pkg_libs = [];
        foreach ($this->libs as $lib) {
            if (isset($lib_meta[$lib::NAME]['pkg-unix'])) {
                $packages = array_merge($packages, $lib_meta[$lib::NAME]['pkg-unix']);
            }
            if (isset($lib_meta[$lib::NAME]['none-pkg-unix'])) {
                $pkg_libs = array_merge($pkg_libs, $lib_meta[$lib::NAME]['none-pkg-unix']);
            }
        }

        $packages = array_unique($packages);
        // -I/usr/include -I/usr/local/include
        // -L/usr/lib -L/usr/local/lib
        $envs = $this->configure_env;
        $envs .= ' CPPFLAGS="-I' . BUILD_ROOT_PATH . '/include  " ' . PHP_EOL;
        $envs .= ' LDFLAGS="-L' . BUILD_ROOT_PATH . '/lib  " ' . PHP_EOL;
        $envs .= ' LIBS=" -lm -lrt -lpthread -lcrypt -lutil  -lresolv " ' . PHP_EOL;
        # $envs .= ' LIBS="-lc++  -libc++  -lm -lrt -lpthread -lcrypt -lutil -lxnet -lresolv " ' . PHP_EOL;
        // for libc -lm -lrt -lpthread -lcrypt -lutil -lxnet -lresolv

        if (!empty($pkg_libs)) {
            $envs .= ' LIBS="$LIBS ' . implode(' ', $pkg_libs) . '"' . PHP_EOL;
        }

        if (!empty($packages)) {
            $envs .= ' export PACKAGES="' . implode(' ', $packages) . '"  ' . PHP_EOL;
            $envs .= ' export CPPFLAGS="$CPPFLAGS $(pkg-config --cflags-only-I --static $PACKAGES )" ' . PHP_EOL;
            $envs .= ' export LDFLAGS="$LDFLAGS $(pkg-config --libs-only-L --static $PACKAGES )" ' . PHP_EOL;
            $envs .= ' export LIBS="$(pkg-config --libs-only-l --static $PACKAGES ) $LIBS" ' . PHP_EOL;
        }
        $cflags .= ' -static '; // -std=gnu11 -idirafter /usr/include -nostdinc /usr/lib
        if (strlen($cflags) > 2) {
            $envs .= " export CFLAGS=\"{$cflags}\" " . PHP_EOL;
        }

        # Patcher::patchPHPBeforeConfigure($this);

        # Patcher::patchPHPConfigure($this);
        $envs .= 'export EXTRA_LDFLAGS_PROGRAM="$LDFLAGS -all-static"';
        shell()
            ->cd(SOURCE_PATH . '/php-src')
            ->exec(
                $envs . PHP_EOL .
                'test -f sapi/cli/php_cli.o && make clean ' . PHP_EOL .
                './buildconf --force' . PHP_EOL .
                './configure ' .
                '--prefix=/' .
                '--with-valgrind=no ' .
                '--enable-shared=no ' .
                '--enable-static=yes ' .
                "--host={$this->gnu_arch}-unknown-linux " .
                '--disable-all ' .
                '--disable-cgi ' .
                '--disable-phpdbg ' .
                '--enable-cli ' .
                '--enable-fpm ' .
                ($this->zts ? '--enable-zts' : '') . ' ' .
                $this->makeExtensionArgs()
            );
        //  '--enable-micro=all-static ' .

        $extra_libs .= $this->generateExtraLibs();
        echo $envs;

        file_put_contents('/tmp/comment', $this->note_section);

        // 清理
        // $this->cleanMake();

        if ($bloat) {
            logger()->info('bloat linking');
            $extra_libs = "-Wl,--whole-archive {$extra_libs} -Wl,--no-whole-archive ";
        }

        if (($build_target & BUILD_TARGET_CLI) === BUILD_TARGET_CLI) {
            logger()->info('building cli');
            $this->buildCli($extra_libs, $use_lld, $envs);
        }
        if (($build_target & BUILD_TARGET_FPM) === BUILD_TARGET_FPM) {
            logger()->info('building fpm');
            $this->buildFpm($extra_libs, $use_lld, $envs);
        }
        if (($build_target & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO) {
            logger()->info('building micro');
            $this->buildMicro($extra_libs, $use_lld, $cflags, $envs);
        }

        if (php_uname('m') === $this->arch) {
            $this->sanityCheck($build_target);
        }

        if ($this->phar_patched) {
            shell()->cd(SOURCE_PATH . '/php-src')->exec('patch -p1 -R < sapi/micro/patches/phar.patch');
        }
    }

    /**
     * @throws RuntimeException
     */
    public function buildCli(string $extra_libs, string $use_lld, string $envs = ''): void
    {
        shell()->cd(SOURCE_PATH . '/php-src')
            // ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec(
                'echo start build' . PHP_EOL .
                $envs . PHP_EOL .
                'make -j' . $this->concurrency .
                ' cli'
            );
        /*
         . '" ' .
            "EXTRA_LIBS=\"{$extra_libs}\" " .
            "EXTRA_LDFLAGS_PROGRAM='{$use_lld} -all-static' " .
         */
        shell()->cd(SOURCE_PATH . '/php-src/sapi/cli')
            ->exec("{$this->cross_compile_prefix}objcopy --only-keep-debug php php.debug")
            ->exec('elfedit --output-osabi linux php')
            ->exec("{$this->cross_compile_prefix}strip --strip-all php")
            ->exec("{$this->cross_compile_prefix}objcopy --update-section .comment=/tmp/comment --add-gnu-debuglink=php.debug --remove-section=.note php");
        $this->deployBinary(BUILD_TARGET_CLI);
    }

    /**
     * @throws RuntimeException
     */
    public function buildMicro(string $extra_libs, string $use_lld, string $cflags, string $envs = ''): void
    {
        if ($this->getPHPVersionID() < 80000) {
            throw new RuntimeException('phpmicro only support PHP >= 8.0!');
        }
        if ($this->getExt('phar')) {
            $this->phar_patched = true;
            try {
                shell()->cd(SOURCE_PATH . '/php-src')->exec('patch -p1 < sapi/micro/patches/phar.patch');
            } catch (RuntimeException $e) {
                logger()->error('failed to patch phat due to patch exit with code ' . $e->getCode());
                $this->phar_patched = false;
            }
        }

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec(
                "make -j{$this->concurrency} " .
                'EXTRA_CFLAGS=' . quote(
                    '-g -Os -fno-ident ' . implode(' ', array_map(fn ($x) => "-Xcompiler {$x}", $this->tune_c_flags))
                ) . ' ' .
                'EXTRA_LIBS=' . quote($extra_libs) . ' ' .
                'EXTRA_LDFLAGS_PROGRAM=' . quote("{$cflags} {$use_lld}" . ' -all-static', "'") . ' ' .
                'micro'
            );

        shell()->cd(SOURCE_PATH . '/php-src/sapi/micro')->exec(
            "{$this->cross_compile_prefix}strip --strip-all micro.sfx"
        );

        $this->deployBinary(BUILD_TARGET_MICRO);
    }

    /**
     * @throws RuntimeException
     */
    public function buildFpm(string $extra_libs, string $use_lld, string $envs = ''): void
    {
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec(
                'make -j' . $this->concurrency .
                ' EXTRA_CFLAGS="-g -Os -fno-ident ' . implode(' ', array_map(fn ($x) => "-Xcompiler {$x}", $this->tune_c_flags)) . '" ' .
                "EXTRA_LIBS=\"{$extra_libs}\" " .
                "EXTRA_LDFLAGS_PROGRAM='{$use_lld} -all-static' " .
                'fpm'
            );

        shell()->cd(SOURCE_PATH . '/php-src/sapi/fpm')
            ->exec("{$this->cross_compile_prefix}objcopy --only-keep-debug php-fpm php-fpm.debug")
            ->exec('elfedit --output-osabi linux php-fpm')
            ->exec("{$this->cross_compile_prefix}strip --strip-all php-fpm")
            ->exec("{$this->cross_compile_prefix}objcopy --update-section .comment=/tmp/comment --add-gnu-debuglink=php-fpm.debug --remove-section=.note php-fpm");
        $this->deployBinary(BUILD_TARGET_FPM);
    }

    /**
     * @throws RuntimeException
     */
    private function generateExtraLibs(): string
    {
        if ($this->libc === 'glibc') {
            $glibc_libs = [
                'rt',
                'm',
                'c',
                'pthread',
                'dl',
                'nsl',
                'anl',
                // 'crypt',
                'resolv',
                'util',
            ];
            $makefile = file_get_contents(SOURCE_PATH . '/php-src/Makefile');
            preg_match('/^EXTRA_LIBS\s*=\s*(.*)$/m', $makefile, $matches);
            if (!$matches) {
                throw new RuntimeException('failed to find EXTRA_LIBS in Makefile');
            }
            $_extra_libs = [];
            foreach (array_filter(explode(' ', $matches[1])) as $used) {
                foreach ($glibc_libs as $libName) {
                    if ("-l{$libName}" === $used && !in_array("-l{$libName}", $_extra_libs, true)) {
                        array_unshift($_extra_libs, "-l{$libName}");
                    }
                }
            }
            return ' ' . implode(' ', $_extra_libs);
        }
        return '';
    }
}
