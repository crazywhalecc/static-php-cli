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
use SPC\util\Patcher;

/**
 * macOS 系统环境下的构建器
 * 源于 Config，但因为感觉叫 Config 不太合适，就换成了 Builder
 */
class MacOSBuilder extends BuilderBase
{
    /** 编译的 Unix 工具集 */
    use UnixBuilderTrait;

    /** @var bool 标记是否 patch 了 phar */
    private bool $phar_patched = false;

    /** @var string env pkgconf */
    private string $pkgconf_env;

    /**
     * @param  null|string         $cc   C编译器名称，如果不传入则默认使用clang
     * @param  null|string         $cxx  C++编译器名称，如果不传入则默认使用clang++
     * @param  null|string         $arch 当前架构，如果不传入则默认使用当前系统架构
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function __construct(?string $cc = null, ?string $cxx = null, ?string $arch = null)
    {
        // 如果是 Debug 模式，才使用 set -x 显示每条执行的命令
        $this->set_x = defined('DEBUG_MODE') ? 'set -x' : 'true';
        // 初始化一些默认参数
        $this->cc = $cc ?? 'clang';
        $this->cxx = $cxx ?? 'clang++';
        $this->arch = $arch ?? php_uname('m');
        $this->gnu_arch = arch2gnu($this->arch);
        // 根据 CPU 线程数设置编译进程数
        $this->concurrency = SystemUtil::getCpuCount();
        // 设置 cflags
        $this->arch_c_flags = SystemUtil::getArchCFlags($this->arch);
        $this->arch_cxx_flags = SystemUtil::getArchCFlags($this->arch);
        // 设置 cmake
        $this->cmake_toolchain_file = SystemUtil::makeCmakeToolchainFile('Darwin', $this->arch, $this->arch_c_flags);

        $build_root_path = BUILD_ROOT_PATH;
        $build_lib_path = BUILD_LIB_PATH;
        // 设置 pkgconfig
        $this->pkgconf_env = 'export PKG_CONFIG_PATH="' . $build_lib_path . '/pkgconfig:/usr/lib/pkgconfig"';
        $this->ld = $this->cc == 'clang' ? 'lld' : 'ld';
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

        // 创立 pkg-config 和放头文件的目录
        f_mkdir(BUILD_ROOT_PATH . '/bin', recursive: true);
        f_mkdir(BUILD_LIB_PATH . '/pkgconfig', recursive: true);
        f_mkdir(BUILD_INCLUDE_PATH, recursive: true);
    }

    /**
     * 生成库构建采用的 autoconf 参数列表
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
            $prefix = $arr[1] ?? null;
            if ($lib instanceof MacOSLibraryBase) {
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
     * 返回 macOS 系统依赖的框架列表
     *
     * @param bool $asString 是否以字符串形式返回（默认为 False）
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
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function buildPHP(int $build_target = BUILD_TARGET_NONE, bool $bloat = false): void
    {
        $extra_libs = $this->getFrameworks(true) . ' ' . ($this->getExt('swoole') ? '-lc++ ' : '');
        if (!$bloat) {
            $extra_libs .= implode(' ', $this->getAllStaticLibFiles());
        } else {
            logger()->info('bloat linking');
            $extra_libs .= implode(
                ' ',
                array_map(
                    fn ($x) => "-Wl,-force_load,{$x}",
                    array_filter($this->getAllStaticLibFiles())
                )
            );
        }

        $envs = '';
        $cflags = $this->arch_c_flags;
        $use_lld = '';

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
        $envs = $this->configure_env;
        $envs .= ' CPPFLAGS="-I' . BUILD_ROOT_PATH . '/include  " ' . PHP_EOL;
        $envs .= ' LDFLAGS="-L' . BUILD_ROOT_PATH . '/lib  " ' . PHP_EOL;
        $envs .= ' LIBS=" -lm  -lpthread  -lutil  -lresolv " ' . PHP_EOL;

        if (!empty($pkg_libs)) {
            $envs .= ' LIBS="$LIBS ' . implode(' ', $pkg_libs) . '"' . PHP_EOL;
        }

        if (!empty($packages)) {
            $envs .= ' export PACKAGES="' . implode(' ', $packages) . '"  ' . PHP_EOL;
            $envs .= ' export CPPFLAGS="$CPPFLAGS $(pkg-config --cflags-only-I --static $PACKAGES )" ' . PHP_EOL;
            $envs .= ' export LDFLAGS="$LDFLAGS $(pkg-config --libs-only-L --static $PACKAGES )" ' . PHP_EOL;
            $envs .= ' export LIBS="$(pkg-config --libs-only-l --static $PACKAGES ) $LIBS" ' . PHP_EOL;
        }
        $cflags .= ' -Wimplicit-function-declaration  ';
        if (strlen($cflags) > 2) {
            $envs .= " export CFLAGS=\"{$cflags}\" " . PHP_EOL;
        }

        $envs .= 'export EXTRA_LDFLAGS_PROGRAM="$LDFLAGS -all-static"';
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec(
                <<<'EOF'
            if [[ -d php_cli_process_title.lo ]] 
            then
                    make clean 
            fi 

EOF
            );
        // patch before configure
        Patcher::patchPHPBeforeConfigure($this);

        Patcher::patchPHPConfigure($this);

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec(
                'echo "start build php"' . PHP_EOL .
                $envs . PHP_EOL .
                './buildconf --force ' . PHP_EOL .
                './configure ' .
                '--prefix= ' .
                '--with-valgrind=no ' .     // 不检测内存泄漏
                '--enable-shared=no ' .
                '--enable-static=yes ' .
                "--host={$this->gnu_arch}-apple-darwin " .
                '--disable-all ' .
                '--disable-cgi ' .
                '--disable-phpdbg ' .
                '--enable-cli ' .
                '--enable-fpm ' .
                '--enable-micro ' .
                ($this->zts ? '--enable-zts' : '') . ' ' .
                $this->makeExtensionArgs() . ' ' .
                $this->configure_env
            );

        $this->cleanMake();

        if (($build_target & BUILD_TARGET_CLI) === BUILD_TARGET_CLI) {
            logger()->info('building cli');
            $this->buildCli($extra_libs, $envs);
        }
        if (($build_target & BUILD_TARGET_FPM) === BUILD_TARGET_FPM) {
            logger()->info('building fpm');
            $this->buildFpm($extra_libs, $envs);
        }
        if (($build_target & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO) {
            logger()->info('building micro');
            $this->buildMicro($extra_libs, $envs);
        }

        if (php_uname('m') === $this->arch) {
            $this->sanityCheck($build_target);
        }

        if ($this->phar_patched) {
            f_passthru('cd ' . SOURCE_PATH . '/php-src && patch -p1 -R < sapi/micro/patches/phar.patch');
        }
    }

    /**
     * 构建 cli
     *
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function buildCli(string $extra_libs, string $envs): void
    {
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec("make -j{$this->concurrency} EXTRA_CFLAGS=\"-g -Os -fno-ident\" EXTRA_LIBS=\"{$extra_libs} -lresolv\" cli")
            ->exec('dsymutil -f sapi/cli/php')
            ->exec('strip sapi/cli/php');
        $this->deployBinary(BUILD_TARGET_CLI);
    }

    /**
     * 构建 phpmicro
     *
     * @throws FileSystemException|RuntimeException
     */
    public function buildMicro(string $extra_libs, string $envs): void
    {
        if ($this->getPHPVersionID() < 80000) {
            throw new RuntimeException('phpmicro only support PHP >= 8.0!');
        }
        if ($this->getExt('phar')) {
            $this->phar_patched = true;
            try {
                // TODO: 未来改进一下 patch，让除了这种 patch 类型的文件以外可以恢复原文件
                shell()->cd(SOURCE_PATH . '/php-src')->exec('patch -p1 < sapi/micro/patches/phar.patch');
            } catch (RuntimeException $e) {
                logger()->error('failed to patch phat due to patch exit with code ' . $e->getCode());
                $this->phar_patched = false;
            }
        }

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec("make -j{$this->concurrency} EXTRA_CFLAGS=\"-g -Os -fno-ident\" EXTRA_LIBS=\"{$extra_libs} -lresolv\" STRIP=\"dsymutil -f \" micro");
        $this->deployBinary(BUILD_TARGET_MICRO);
    }

    /**
     * 构建 fpm
     *
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function buildFpm(string $extra_libs, string $envs): void
    {
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec("make -j{$this->concurrency} EXTRA_CFLAGS=\"-g -Os -fno-ident\" EXTRA_LIBS=\"{$extra_libs} -lresolv\" fpm")
            ->exec('dsymutil -f sapi/fpm/php-fpm')
            ->exec('strip sapi/fpm/php-fpm');
        $this->deployBinary(BUILD_TARGET_FPM);
    }
}
