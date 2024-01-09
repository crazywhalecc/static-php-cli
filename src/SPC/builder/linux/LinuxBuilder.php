<?php

declare(strict_types=1);

namespace SPC\builder\linux;

use SPC\builder\BuilderBase;
use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\traits\UnixBuilderTrait;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
use SPC\store\SourcePatcher;

class LinuxBuilder extends BuilderBase
{
    /** Unix compatible builder methods */
    use UnixBuilderTrait;

    /** @var array Tune cflags */
    public array $tune_c_flags;

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
        // set C/C++ compilers (default: alpine: gcc, others: musl-cross-make)
        if (SystemUtil::isMuslDist()) {
            f_putenv("CC={$this->getOption('cc', 'gcc')}");
            f_putenv("CXX={$this->getOption('cxx', 'g++')}");
            f_putenv("AR={$this->getOption('ar', 'ar')}");
            f_putenv("LD={$this->getOption('ld', 'ld.gold')}");
        } else {
            $arch = arch2gnu(php_uname('m'));
            f_putenv("CC={$this->getOption('cc', "{$arch}-linux-musl-gcc")}");
            f_putenv("CXX={$this->getOption('cxx', "{$arch}-linux-musl-g++")}");
            f_putenv("AR={$this->getOption('ar', "{$arch}-linux-musl-ar")}");
            f_putenv("LD={$this->getOption('ld', 'ld.gold')}");
            f_putenv("PATH=/usr/local/musl/bin:/usr/local/musl/{$arch}-linux-musl/bin:" . BUILD_ROOT_PATH . '/bin:' . getenv('PATH'));

            // set library path, some libraries need it. (We cannot use `putenv` here, because cmake will be confused)
            $this->setOptionIfNotExist('library_path', "LIBRARY_PATH=/usr/local/musl/{$arch}-linux-musl/lib");
            $this->setOptionIfNotExist('ld_library_path', "LD_LIBRARY_PATH=/usr/local/musl/{$arch}-linux-musl/lib");

            // check musl-cross make installed if we use musl-cross-make
            if (str_ends_with(getenv('CC'), 'linux-musl-gcc') && !file_exists("/usr/local/musl/bin/{$arch}-linux-musl-gcc")) {
                throw new WrongUsageException('musl-cross-make not installed, please install it first. (You can use `doctor` command to install it)');
            }
        }

        // set PKG_CONFIG
        f_putenv('PKG_CONFIG=' . BUILD_ROOT_PATH . '/bin/pkg-config');
        // set PKG_CONFIG_PATH
        f_putenv('PKG_CONFIG_PATH=' . BUILD_LIB_PATH . '/pkgconfig');

        // set arch (default: current)
        $this->setOptionIfNotExist('arch', php_uname('m'));
        $this->setOptionIfNotExist('gnu-arch', arch2gnu($this->getOption('arch')));

        // concurrency
        $this->concurrency = SystemUtil::getCpuCount();
        // cflags
        $this->arch_c_flags = SystemUtil::getArchCFlags(getenv('CC'), $this->getOption('arch'));
        $this->arch_cxx_flags = SystemUtil::getArchCFlags(getenv('CXX'), $this->getOption('arch'));
        $this->tune_c_flags = SystemUtil::checkCCFlags(SystemUtil::getTuneCFlags($this->getOption('arch')), getenv('CC'));
        // cmake toolchain
        $this->cmake_toolchain_file = SystemUtil::makeCmakeToolchainFile(
            'Linux',
            $this->getOption('arch'),
            $this->arch_c_flags,
            getenv('CC'),
            getenv('CXX'),
        );

        // cross-compiling is not supported yet
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
        // add libstdc++, some extensions or libraries need it
        $extra_libs .= (empty($extra_libs) ? '' : ' ') . ($this->hasCpp() ? '-lstdc++ ' : '');
        $this->setOption('extra-libs', $extra_libs);
        $cflags = $this->arch_c_flags;

        // prepare build php envs
        $envs_build_php = SystemUtil::makeEnvVarString([
            'CFLAGS' => $cflags,
            'CPPFLAGS' => '-I' . BUILD_INCLUDE_PATH,
            'LDFLAGS' => '-L' . BUILD_LIB_PATH,
            'LIBS' => '-ldl -lpthread',
        ]);

        $this->emitPatchPoint('before-php-buildconf');
        SourcePatcher::patchBeforeBuildconf($this);

        shell()->cd(SOURCE_PATH . '/php-src')->exec('./buildconf --force');

        $this->emitPatchPoint('before-php-configure');
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
        $disable_jit = $this->getOption('disable-opcache-jit', false) ? '--disable-opcache-jit ' : '';

        $enableCli = ($build_target & BUILD_TARGET_CLI) === BUILD_TARGET_CLI;
        $enableFpm = ($build_target & BUILD_TARGET_FPM) === BUILD_TARGET_FPM;
        $enableMicro = ($build_target & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO;
        $enableEmbed = ($build_target & BUILD_TARGET_EMBED) === BUILD_TARGET_EMBED;

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec(
                "{$this->getOption('ld_library_path')} " .
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
                ($enableEmbed ? '--enable-embed=static ' : '--disable-embed ') .
                ($enableMicro ? '--enable-micro=all-static ' : '--disable-micro ') .
                $disable_jit .
                $json_74 .
                $zts .
                $maxExecutionTimers .
                $this->makeExtensionArgs() .
                ' ' . $envs_build_php . ' '
            );

        $this->emitPatchPoint('before-php-make');
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
            $this->emitPatchPoint('before-sanity-check');
            $this->sanityCheck($build_target);
        }
    }

    /**
     * Build cli sapi
     *
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function buildCli(): void
    {
        $vars = SystemUtil::makeEnvVarString($this->getBuildVars());
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
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function buildMicro(): void
    {
        if ($this->getPHPVersionID() < 80000) {
            throw new WrongUsageException('phpmicro only support PHP >= 8.0!');
        }
        if ($this->getExt('phar')) {
            $this->phar_patched = true;
            SourcePatcher::patchMicro(['phar']);
        }

        $vars = SystemUtil::makeEnvVarString($this->getBuildVars([
            'EXTRA_CFLAGS' => $this->getOption('with-micro-fake-cli', false) ? ' -DPHP_MICRO_FAKE_CLI' : '',
        ]));
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
    protected function buildFpm(): void
    {
        $vars = SystemUtil::makeEnvVarString($this->getBuildVars());
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec("make -j{$this->concurrency} {$vars} fpm");

        if (!$this->getOption('no-strip', false)) {
            shell()->cd(SOURCE_PATH . '/php-src/sapi/fpm')->exec('strip --strip-all php-fpm');
        }

        $this->deployBinary(BUILD_TARGET_FPM);
    }

    /**
     * Build embed sapi
     *
     * @throws RuntimeException
     */
    protected function buildEmbed(): void
    {
        $vars = SystemUtil::makeEnvVarString($this->getBuildVars());

        shell()
            ->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec('make INSTALL_ROOT=' . BUILD_ROOT_PATH . " -j{$this->concurrency} {$vars} install");
    }

    private function getBuildVars($input = []): array
    {
        $use_lld = '';
        if (str_ends_with(getenv('CC'), 'clang') && SystemUtil::findCommand('lld')) {
            $use_lld = '-Xcompiler -fuse-ld=lld';
        }
        $optimization = $this->getOption('no-strip', false) ? '-g -O0' : '-g0 -Os';
        $cflags = isset($input['EXTRA_CFLAGS']) && $input['EXTRA_CFLAGS'] ? " {$input['EXTRA_CFLAGS']}" : '';
        $libs = isset($input['EXTRA_LIBS']) && $input['EXTRA_LIBS'] ? " {$input['EXTRA_LIBS']}" : '';
        $ldflags = isset($input['EXTRA_LDFLAGS_PROGRAM']) && $input['EXTRA_LDFLAGS_PROGRAM'] ? " {$input['EXTRA_LDFLAGS_PROGRAM']}" : '';
        $tune_c_flags = implode(' ', array_map(fn ($x) => "-Xcompiler {$x}", $this->tune_c_flags));
        return [
            'EXTRA_CFLAGS' => "{$optimization} -fno-ident -fPIE {$tune_c_flags}{$cflags}",
            'EXTRA_LIBS' => "{$this->getOption('extra-libs', '')} {$libs}",
            'EXTRA_LDFLAGS_PROGRAM' => "{$use_lld} -all-static{$ldflags}",
        ];
    }
}
