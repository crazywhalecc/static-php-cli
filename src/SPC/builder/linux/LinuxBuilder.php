<?php

declare(strict_types=1);

namespace SPC\builder\linux;

use SPC\builder\BuilderBase;
use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\traits\UnixBuilderTrait;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\Patcher;

/**
 * Linux 系统环境下的构建器
 */
class LinuxBuilder extends BuilderBase
{
    /** 编译的 Unix 工具集 */
    use UnixBuilderTrait;

    /** @var string[] Linux 环境下编译依赖的命令 */
    public const REQUIRED_COMMANDS = ['make', 'bison', 'flex', 'pkg-config', 'git', 'autoconf', 'automake', 'tar', 'unzip', /* 'xz', 好像不需要 */ 'gzip', 'bzip2', 'cmake'];

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
     */
    public function __construct(?string $cc = null, ?string $cxx = null, ?string $arch = null)
    {
        // 初始化一些默认参数
        $this->cc = $cc ?? 'musl-gcc';
        $this->cxx = $cxx ?? 'g++';
        $this->arch = $arch ?? php_uname('m');
        $this->gnu_arch = arch2gnu($this->arch);
        $this->libc = 'musl'; // SystemUtil::selectLibc($this->cc);

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
        // 设置 pkgconfig
        $this->pkgconf_env = 'PKG_CONFIG_PATH="' . BUILD_LIB_PATH . '/pkgconfig"';
        // 设置 configure 依赖的环境变量
        $this->configure_env =
            $this->pkgconf_env . ' ' .
            "CC='{$this->cc}' " .
            "CXX='{$this->cxx}' " .
            (php_uname('m') === $this->arch ? '' : "CFLAGS='{$this->arch_c_flags}'");
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
            throw new RuntimeException('missing system commands: ' . implode(', ', $missing));
        }

        // 创立 pkg-config 和放头文件的目录
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
    public function buildPHP(int $build_micro_rule = BUILD_MICRO_NONE, bool $with_clean = false, bool $bloat = false)
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

        $envs = $this->pkgconf_env . ' ' .
            "CC='{$this->cc}' " .
            "CXX='{$this->cxx}' ";
        $cflags = $this->arch_c_flags;
        $use_lld = '';

        switch ($this->libc) {
            case 'musl_wrapper':
            case 'glibc':
                $cflags .= ' -static-libgcc -I"' . BUILD_INCLUDE_PATH . '"';
                break;
            case 'musl':
                if (str_ends_with($this->cc, 'clang') && SystemUtil::findCommand('lld')) {
                    $use_lld = '-Xcompiler -fuse-ld=lld';
                }
                break;
            default:
                throw new RuntimeException('libc ' . $this->libc . ' is not implemented yet');
        }

        $envs = "{$envs} CFLAGS='{$cflags}' LIBS='-ldl -lpthread'";

        Patcher::patchPHPBeforeConfigure($this);

        f_passthru(
            $this->set_x . ' && ' .
            'cd ' . SOURCE_PATH . '/php-src && ' .
            './buildconf --force'
        );

        Patcher::patchPHPConfigure($this);

        f_passthru(
            $this->set_x . ' && ' .
            'cd ' . SOURCE_PATH . '/php-src && ' .
            './configure ' .
            '--prefix= ' .
            '--with-valgrind=no ' .
            '--enable-shared=no ' .
            '--enable-static=yes ' .
            "--host={$this->gnu_arch}-unknown-linux " .
            '--disable-all ' .
            '--disable-cgi ' .
            '--disable-phpdbg ' .
            '--enable-cli ' .
            '--enable-micro=all-static ' .
            ($this->zts ? '--enable-zts' : '') . ' ' .
            $this->makeExtensionArgs() . ' ' .
            $envs
        );

        $extra_libs .= $this->generateExtraLibs();

        file_put_contents('/tmp/comment', $this->note_section);

        if ($with_clean) {
            logger()->info('cleaning up');
            f_passthru(
                $this->set_x . ' && ' .
                'cd ' . SOURCE_PATH . '/php-src && ' .
                'make clean'
            );
        }

        if ($bloat) {
            logger()->info('bloat linking');
            $extra_libs = "-Wl,--whole-archive {$extra_libs} -Wl,--no-whole-archive";
        }

        switch ($build_micro_rule) {
            case BUILD_MICRO_NONE:
                logger()->info('building cli');
                $this->buildCli($extra_libs, $use_lld);
                break;
            case BUILD_MICRO_ONLY:
                logger()->info('building micro');
                $this->buildMicro($extra_libs, $use_lld, $cflags);
                break;
            case BUILD_MICRO_BOTH:
                logger()->info('building cli and micro');
                $this->buildCli($extra_libs, $use_lld);
                $this->buildMicro($extra_libs, $use_lld, $cflags);
                break;
        }

        if (php_uname('m') === $this->arch) {
            $this->sanityCheck($build_micro_rule);
        }

        if ($this->phar_patched) {
            f_passthru('cd ' . SOURCE_PATH . '/php-src && patch -p1 -R < sapi/micro/patches/phar.patch');
        }
    }

    /**
     * @throws RuntimeException
     */
    public function buildCli(string $extra_libs, string $use_lld): void
    {
        f_passthru(
            $this->set_x . ' && ' .
            'cd ' . SOURCE_PATH . '/php-src && ' .
            'sed -i "s|//lib|/lib|g" Makefile && ' .
            "make -j{$this->concurrency} " .
            'EXTRA_CFLAGS="-g -Os -fno-ident ' . implode(' ', array_map(fn ($x) => "-Xcompiler {$x}", $this->tune_c_flags)) . '" ' .
            "EXTRA_LIBS=\"{$extra_libs}\" " .
            "EXTRA_LDFLAGS_PROGRAM='{$use_lld}" .
            ' -all-static' .
            "' " .
            'cli && ' .
            'cd sapi/cli && ' .
            "{$this->cross_compile_prefix}objcopy --only-keep-debug php php.debug && " .
            'elfedit --output-osabi linux php && ' .
            "{$this->cross_compile_prefix}strip --strip-all php && " .
            "{$this->cross_compile_prefix}objcopy --update-section .comment=/tmp/comment --add-gnu-debuglink=php.debug --remove-section=.note php"
        );
    }

    /**
     * @throws RuntimeException
     */
    public function buildMicro(string $extra_libs, string $use_lld, string $cflags): void
    {
        if ($this->getExt('phar')) {
            $this->phar_patched = true;
            try {
                f_passthru('cd ' . SOURCE_PATH . '/php-src && patch -p1 < sapi/micro/patches/phar.patch');
            } catch (RuntimeException $e) {
                logger()->error('failed to patch phat due to patch exit with code ' . $e->getCode());
                $this->phar_patched = false;
            }
        }

        $vars = [
            'EXTRA_CFLAGS' => quote('-g -Os -fno-ident ' . implode(' ', array_map(fn ($x) => "-Xcompiler {$x}", $this->tune_c_flags))),
            'EXTRA_LIBS' => quote($extra_libs),
            'EXTRA_LDFLAGS_PROGRAM' => quote("{$cflags} {$use_lld}" . ' -all-static', "'"),
            'POST_MICRO_BUILD_COMMANDS' => quote(
                "sh -xc '" .
                'cd sapi/micro && ' .
                "{$this->cross_compile_prefix}objcopy --only-keep-debug micro.sfx micro.sfx.debug && " .
                'elfedit --output-osabi linux micro.sfx && ' .
                "{$this->cross_compile_prefix}strip --strip-all micro.sfx && " .
                "{$this->cross_compile_prefix}objcopy --update-section .comment=/tmp/comment --add-gnu-debuglink=micro.sfx.debug --remove-section=.note micro.sfx'"
            ),
        ];
        $var_cmdline = '';
        foreach ($vars as $k => $v) {
            $var_cmdline .= $k . '=' . $v . ' ';
        }

        f_passthru(
            $this->set_x . ' && ' .
            'cd ' . SOURCE_PATH . '/php-src && ' .
            'sed -i "s|//lib|/lib|g" Makefile && ' .
            "make -j{$this->concurrency} " .
            $var_cmdline .
            'micro'
        );
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
