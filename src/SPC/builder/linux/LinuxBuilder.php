<?php

declare(strict_types=1);

namespace SPC\builder\linux;

use SPC\builder\BuilderBase;
use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\traits\UnixBuilderTrait;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\SourcePatcher;

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
     * @throws WrongUsageException
     */
    public function __construct(?string $cc = null, ?string $cxx = null, ?string $arch = null, bool $zts = false)
    {
        // 初始化一些默认参数
        $this->cc = $cc ?? match (SystemUtil::getOSRelease()['dist']) {
            'alpine' => 'gcc',
            default => 'musl-gcc'
        };
        $this->cxx = $cxx ?? 'g++';
        $this->arch = $arch ?? php_uname('m');
        $this->gnu_arch = arch2gnu($this->arch);
        $this->zts = $zts;
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
        $this->pkgconf_env = 'PKG_CONFIG="' . BUILD_ROOT_PATH . '/bin/pkg-config" PKG_CONFIG_PATH="' . BUILD_LIB_PATH . '/pkgconfig"';
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
            throw new WrongUsageException('missing system commands: ' . implode(', ', $missing));
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
        if ($this->getExt('swoole') || $this->getExt('intl')) {
            $extra_libs .= ' -lstdc++';
        }
        if ($this->getExt('imagick')) {
            $extra_libs .= ' /usr/lib/libMagick++-7.Q16HDRI.a /usr/lib/libMagickCore-7.Q16HDRI.a /usr/lib/libMagickWand-7.Q16HDRI.a';
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
                throw new WrongUsageException('libc ' . $this->libc . ' is not implemented yet');
        }

        $envs = "{$envs} CFLAGS='{$cflags}' LIBS='-ldl -lpthread'";

        SourcePatcher::patchPHPBuildconf($this);

        shell()->cd(SOURCE_PATH . '/php-src')->exec('./buildconf --force');

        SourcePatcher::patchPHPConfigure($this);

        if ($this->getPHPVersionID() < 80000) {
            $json_74 = '--enable-json ';
        } else {
            $json_74 = '';
        }

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec(
                './configure ' .
                '--prefix= ' .
                '--with-valgrind=no ' .
                '--enable-shared=no ' .
                '--enable-static=yes ' .
                '--disable-all ' .
                '--disable-cgi ' .
                '--disable-phpdbg ' .
                '--enable-cli ' .
                '--enable-fpm ' .
                $json_74 .
                '--enable-micro=all-static ' .
                ($this->zts ? '--enable-zts' : '') . ' ' .
                $this->makeExtensionArgs() . ' ' .
                $envs
            );

        SourcePatcher::patchPHPAfterConfigure($this);

        file_put_contents('/tmp/comment', $this->note_section);

        // 清理
        $this->cleanMake();

        if ($bloat) {
            logger()->info('bloat linking');
            $extra_libs = "-Wl,--whole-archive {$extra_libs} -Wl,--no-whole-archive";
        }

        if (($build_target & BUILD_TARGET_CLI) === BUILD_TARGET_CLI) {
            logger()->info('building cli');
            $this->buildCli($extra_libs, $use_lld);
        }
        if (($build_target & BUILD_TARGET_FPM) === BUILD_TARGET_FPM) {
            logger()->info('building fpm');
            $this->buildFpm($extra_libs, $use_lld);
        }
        if (($build_target & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO) {
            logger()->info('building micro');
            $this->buildMicro($extra_libs, $use_lld, $cflags);
        }

        if (php_uname('m') === $this->arch) {
            $this->sanityCheck($build_target);
        }

        if ($this->phar_patched) {
            SourcePatcher::patchMicro(['phar'], true);
        }
    }

    /**
     * @throws RuntimeException
     */
    public function buildCli(string $extra_libs, string $use_lld): void
    {
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec(
                'make -j' . $this->concurrency .
                ' EXTRA_CFLAGS="-g -Os -fno-ident ' . implode(' ', array_map(fn ($x) => "-Xcompiler {$x}", $this->tune_c_flags)) . '" ' .
                "EXTRA_LIBS=\"{$extra_libs}\" " .
                "EXTRA_LDFLAGS_PROGRAM='{$use_lld} -all-static' " .
                'cli'
            );

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
    public function buildMicro(string $extra_libs, string $use_lld, string $cflags): void
    {
        if ($this->getPHPVersionID() < 80000) {
            throw new RuntimeException('phpmicro only support PHP >= 8.0!');
        }
        if ($this->getExt('phar')) {
            $this->phar_patched = true;
            SourcePatcher::patchMicro(['phar']);
        }

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec(
                "make -j{$this->concurrency} " .
                'EXTRA_CFLAGS=' . quote('-g -Os -fno-ident ' . implode(' ', array_map(fn ($x) => "-Xcompiler {$x}", $this->tune_c_flags))) . ' ' .
                'EXTRA_LIBS=' . quote($extra_libs) . ' ' .
                'EXTRA_LDFLAGS_PROGRAM=' . quote("{$cflags} {$use_lld}" . ' -all-static', "'") . ' ' .
                'micro'
            );

        shell()->cd(SOURCE_PATH . '/php-src/sapi/micro')->exec("{$this->cross_compile_prefix}strip --strip-all micro.sfx");

        $this->deployBinary(BUILD_TARGET_MICRO);
    }

    /**
     * 构建 fpm
     *
     * @throws FileSystemException|RuntimeException
     */
    public function buildFpm(string $extra_libs, string $use_lld): void
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
}
