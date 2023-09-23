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

class LinuxBuilder extends BuilderBase
{
    /** Unix compatible builder methods */
    use UnixBuilderTrait;

    /** @var string Using libc [musl,glibc] */
    public string $libc;

    /** @var array Tune cflags */
    public array $tune_c_flags;

    /** @var string pkg-config env, including PKG_CONFIG_PATH, PKG_CONFIG */
    public string $pkgconf_env;

    /** @var bool Micro patch phar flag */
    private bool $phar_patched = false;

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;

        // ---------- set necessary options ----------
        // set C Compiler (default: alpine: gcc, others: musl-gcc)
        $this->setOptionIfNotExist('cc', match (SystemUtil::getOSRelease()['dist']) {
            'alpine' => 'gcc',
            default => 'musl-gcc'
        });
        // set C++ Compiler (default: g++)
        $this->setOptionIfNotExist('cxx', 'g++');
        // set arch (default: current)
        $this->setOptionIfNotExist('arch', php_uname('m'));
        $this->setOptionIfNotExist('gnu-arch', arch2gnu($this->getOption('arch')));

        // ---------- set necessary compile environments ----------
        // set libc
        $this->libc = 'musl'; // SystemUtil::selectLibc($this->cc);
        // concurrency
        $this->concurrency = SystemUtil::getCpuCount();
        // cflags
        $this->arch_c_flags = SystemUtil::getArchCFlags($this->getOption('cc'), $this->getOption('arch'));
        $this->arch_cxx_flags = SystemUtil::getArchCFlags($this->getOption('cxx'), $this->getOption('arch'));
        $this->tune_c_flags = SystemUtil::checkCCFlags(SystemUtil::getTuneCFlags($this->getOption('arch')), $this->getOption('cc'));
        // cmake toolchain
        $this->cmake_toolchain_file = SystemUtil::makeCmakeToolchainFile(
            'Linux',
            $this->getOption('arch'),
            $this->arch_c_flags,
            $this->getOption('cc'),
            $this->getOption('cxx'),
        );
        // pkg-config
        $vars = [
            'PKG_CONFIG' => BUILD_ROOT_PATH . '/bin/pkg-config',
            'PKG_CONFIG_PATH' => BUILD_LIB_PATH . '/pkgconfig',
        ];
        $this->pkgconf_env = SystemUtil::makeEnvVarString($vars);
        // configure environment
        $this->configure_env = SystemUtil::makeEnvVarString([
            ...$vars,
            'CC' => $this->getOption('cc'),
            'CXX' => $this->getOption('cxx'),
            'PATH' => BUILD_ROOT_PATH . '/bin:' . getenv('PATH'),
        ]);
        // cross-compile does not support yet
        /*if (php_uname('m') !== $this->arch) {
            $this->cross_compile_prefix = SystemUtil::getCrossCompilePrefix($this->cc, $this->arch);
            logger()->info('using cross compile prefix: ' . $this->cross_compile_prefix);
            $this->configure_env .= " CROSS_COMPILE='{$this->cross_compile_prefix}'";
        }*/

        // create pkgconfig and include dir (some libs cannot create them automatically)
        f_mkdir(BUILD_LIB_PATH . '/pkgconfig', recursive: true);
        f_mkdir(BUILD_INCLUDE_PATH, recursive: true);
    }

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
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
     * @throws WrongUsageException
     */
    public function buildPHP(int $build_target = BUILD_TARGET_NONE): void
    {
        // ---------- Update extra-libs ----------
        $extra_libs = $this->getOption('extra-libs', '');
        // non-bloat linking
        if (!$this->getOption('bloat', false)) {
            $extra_libs .= (empty($extra_libs) ? '' : ' ') . implode(' ', $this->getAllStaticLibFiles());
        } else {
            $extra_libs .= (empty($extra_libs) ? '' : ' ') . implode(' ', array_map(fn ($x) => "-Xcompiler {$x}", array_filter($this->getAllStaticLibFiles())));
        }
        // add libstdc++, some extensions or libraries need it (C++ cannot be linked statically)
        $extra_libs .= (empty($extra_libs) ? '' : ' ') . ($this->hasCppExtension() ? '-lstdc++ ' : '');
        $this->setOption('extra-libs', $extra_libs);

        $cflags = $this->arch_c_flags;
        $use_lld = '';

        switch ($this->libc) {
            case 'musl_wrapper':
            case 'glibc':
                $cflags .= ' -static-libgcc -I"' . BUILD_INCLUDE_PATH . '"';
                break;
            case 'musl':
                if (str_ends_with($this->getOption('cc'), 'clang') && SystemUtil::findCommand('lld')) {
                    $use_lld = '-Xcompiler -fuse-ld=lld';
                }
                break;
            default:
                throw new WrongUsageException('libc ' . $this->libc . ' is not implemented yet');
        }

        $envs = $this->pkgconf_env . ' ' . SystemUtil::makeEnvVarString([
            'CC' => $this->getOption('cc'),
            'CXX' => $this->getOption('cxx'),
            'CFLAGS' => $cflags,
            'LIBS' => '-ldl -lpthread',
            'PATH' => BUILD_ROOT_PATH . '/bin:' . getenv('PATH'),
        ]);

        SourcePatcher::patchBeforeBuildconf($this);

        shell()->cd(SOURCE_PATH . '/php-src')->exec('./buildconf --force');

        SourcePatcher::patchBeforeConfigure($this);

        $phpVersionID = $this->getPHPVersionID();
        $json_74 = $phpVersionID < 80000 ? '--enable-json ' : '';

        if ($this->getOption('enable-zts', false)) {
            $maxExecutionTimers = $phpVersionID >= 80100 ? '--enable-zend-max-execution-timers ' : '';
            $zts = '--enable-zts --disable-zend-signals ';
        } else {
            $maxExecutionTimers = '';
            $zts = '';
        }

        $enableCli = ($build_target & BUILD_TARGET_CLI) === BUILD_TARGET_CLI;
        $enableFpm = ($build_target & BUILD_TARGET_FPM) === BUILD_TARGET_FPM;
        $enableMicro = ($build_target & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO;
        $enableEmbed = ($build_target & BUILD_TARGET_EMBED) === BUILD_TARGET_EMBED;

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
                ($enableCli ? '--enable-cli ' : '--disable-cli ') .
                ($enableFpm ? '--enable-fpm ' : '--disable-fpm ') .
                ($enableEmbed ? '--enable-embed=static --disable-opcache-jit ' : '--disable-embed ') .
                $json_74 .
                $zts .
                $maxExecutionTimers .
                ($enableMicro ? '--enable-micro=all-static ' : '--disable-micro ') .
                $this->makeExtensionArgs() . ' ' .
                $envs
            );

        SourcePatcher::patchBeforeMake($this);

        $this->cleanMake();

        if ($enableCli) {
            logger()->info('building cli');
            $this->buildCli($extra_libs, $use_lld);
        }
        if ($enableFpm) {
            logger()->info('building fpm');
            $this->buildFpm($extra_libs, $use_lld);
        }
        if ($enableMicro) {
            logger()->info('building micro');
            $this->buildMicro($extra_libs, $use_lld, $cflags);
        }
        if ($enableEmbed) {
            logger()->info('building embed');
            $this->buildEmbed($extra_libs, $use_lld);
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
    public function buildCli(string $extra_libs, string $use_lld): void
    {
        $vars = SystemUtil::makeEnvVarString([
            'EXTRA_CFLAGS' => '-g -Os -fno-ident ' . implode(' ', array_map(fn ($x) => "-Xcompiler {$x}", $this->tune_c_flags)),
            'EXTRA_LIBS' => $extra_libs,
            'EXTRA_LDFLAGS_PROGRAM' => "{$use_lld} -all-static",
        ]);
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec("make -j{$this->concurrency} {$vars} cli");

        if (!$this->getOption('no-strip', false)) {
            shell()->cd(SOURCE_PATH . '/php-src/sapi/cli')->exec('strip --strip-all php');
        }

        $this->deployBinary(BUILD_TARGET_CLI);
    }

    /**
     * Build phpmicro sapi
     *
     * @throws RuntimeException
     * @throws FileSystemException
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

        $enable_fake_cli = $this->getOption('with-micro-fake-cli', false) ? ' -DPHP_MICRO_FAKE_CLI' : '';
        $vars = SystemUtil::makeEnvVarString([
            'EXTRA_CFLAGS' => '-g -Os -fno-ident ' . implode(' ', array_map(fn ($x) => "-Xcompiler {$x}", $this->tune_c_flags)) . $enable_fake_cli,
            'EXTRA_LIBS' => $extra_libs,
            'EXTRA_LDFLAGS_PROGRAM' => "{$cflags} {$use_lld} -all-static",
        ]);
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec("make -j{$this->concurrency} {$vars} micro");

        if (!$this->getOption('no-strip', false)) {
            shell()->cd(SOURCE_PATH . '/php-src/sapi/micro')->exec('strip --strip-all micro.sfx');
        }

        $this->deployBinary(BUILD_TARGET_MICRO);

        if ($this->phar_patched) {
            SourcePatcher::patchMicro(['phar'], true);
        }
    }

    /**
     * Build fpm sapi
     *
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function buildFpm(string $extra_libs, string $use_lld): void
    {
        $vars = SystemUtil::makeEnvVarString([
            'EXTRA_CFLAGS' => '-g -Os -fno-ident ' . implode(' ', array_map(fn ($x) => "-Xcompiler {$x}", $this->tune_c_flags)),
            'EXTRA_LIBS' => $extra_libs,
            'EXTRA_LDFLAGS_PROGRAM' => "{$use_lld} -all-static",
        ]);

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec("make -j{$this->concurrency} {$vars} fpm");

        if (!$this->getOption('no-strip', false)) {
            shell()->cd(SOURCE_PATH . '/php-src/sapi/fpm')->exec('strip --strip-all php-fpm');
        }

        $this->deployBinary(BUILD_TARGET_FPM);
    }

    public function buildEmbed(string $extra_libs, string $use_lld): void
    {
        $vars = SystemUtil::makeEnvVarString([
            'EXTRA_CFLAGS' => '-g -Os -fno-ident ' . implode(' ', array_map(fn ($x) => "-Xcompiler {$x}", $this->tune_c_flags)),
            'EXTRA_LIBS' => $extra_libs,
            'EXTRA_LDFLAGS_PROGRAM' => "{$use_lld} -all-static",
        ]);

        shell()
            ->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec('make INSTALL_ROOT=' . BUILD_ROOT_PATH . " -j{$this->concurrency} {$vars} install");
    }
}
