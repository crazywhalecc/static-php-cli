<?php

declare(strict_types=1);

namespace SPC\builder\linux;

use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\unix\UnixBuilderBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
use SPC\store\SourcePatcher;
use SPC\util\GlobalEnvManager;

class LinuxBuilder extends UnixBuilderBase
{
    /** @var bool Micro patch phar flag */
    private bool $phar_patched = false;

    /**
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;

        // check musl-cross make installed if we use musl-cross-make
        $arch = arch2gnu(php_uname('m'));

        // set library path, some libraries need it. (We cannot use `putenv` here, because cmake will be confused)
        $this->setOptionIfNotExist('library_path', "LIBRARY_PATH=/usr/local/musl/{$arch}-linux-musl/lib");
        $this->setOptionIfNotExist('ld_library_path', "LD_LIBRARY_PATH=/usr/local/musl/{$arch}-linux-musl/lib");

        GlobalEnvManager::init($this);

        if (str_ends_with(getenv('CC'), 'linux-musl-gcc') && !file_exists("/usr/local/musl/bin/{$arch}-linux-musl-gcc")) {
            throw new WrongUsageException('musl-cross-make not installed, please install it first. (You can use `doctor` command to install it)');
        }

        // concurrency
        $this->concurrency = intval(getenv('SPC_CONCURRENCY'));
        // cflags
        $this->arch_c_flags = getenv('SPC_DEFAULT_C_FLAGS');
        $this->arch_cxx_flags = getenv('SPC_DEFAULT_CXX_FLAGS');
        // cmake toolchain
        $this->cmake_toolchain_file = SystemUtil::makeCmakeToolchainFile(
            'Linux',
            $arch,
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
        $extra_libs = getenv('SPC_EXTRA_LIBS') ?: '';
        // bloat means force-load all static libraries, even if they are not used
        if (!$this->getOption('bloat', false)) {
            $extra_libs .= (empty($extra_libs) ? '' : ' ') . implode(' ', $this->getAllStaticLibFiles());
        } else {
            $extra_libs .= (empty($extra_libs) ? '' : ' ') . implode(' ', array_map(fn ($x) => "-Xcompiler {$x}", array_filter($this->getAllStaticLibFiles())));
        }
        // add libstdc++, some extensions or libraries need it
        $extra_libs .= (empty($extra_libs) ? '' : ' ') . ($this->hasCpp() ? '-lstdc++ ' : '');
        f_putenv('SPC_EXTRA_LIBS=' . $extra_libs);
        $cflags = $this->arch_c_flags;

        $this->emitPatchPoint('before-php-buildconf');
        SourcePatcher::patchBeforeBuildconf($this);

        shell()->cd(SOURCE_PATH . '/php-src')->exec(getenv('SPC_CMD_PREFIX_PHP_BUILDCONF'));

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

        // prepare build php envs
        $envs_build_php = SystemUtil::makeEnvVarString([
            'CFLAGS' => getenv('SPC_CMD_VAR_PHP_CONFIGURE_CFLAGS'),
            'CPPFLAGS' => getenv('SPC_CMD_VAR_PHP_CONFIGURE_CPPFLAGS'),
            'LDFLAGS' => getenv('SPC_CMD_VAR_PHP_CONFIGURE_LDFLAGS'),
            'LIBS' => getenv('SPC_CMD_VAR_PHP_CONFIGURE_LIBS'),
        ]);

        // upx pack and strip for micro
        if ($this->getOption('with-upx-pack', false)) {
            // with upx pack always need strip
            FileSystem::replaceFileRegex(
                SOURCE_PATH . '/php-src/sapi/micro/Makefile.frag',
                '/POST_MICRO_BUILD_COMMANDS=.*/',
                'POST_MICRO_BUILD_COMMANDS=\$(STRIP) \$(MICRO_STRIP_FLAGS) \$(SAPI_MICRO_PATH) && ' . getenv('UPX_EXEC') . ' --best \$(SAPI_MICRO_PATH)',
            );
        } elseif (!$this->getOption('no-strip', false)) {
            // not-no-strip means strip (default behavior)
            FileSystem::replaceFileRegex(
                SOURCE_PATH . '/php-src/sapi/micro/Makefile.frag',
                '/POST_MICRO_BUILD_COMMANDS=.*/',
                'POST_MICRO_BUILD_COMMANDS=\$(STRIP) \$(MICRO_STRIP_FLAGS) \$(SAPI_MICRO_PATH)',
            );
        } else {
            // just no strip
            FileSystem::replaceFileRegex(
                SOURCE_PATH . '/php-src/sapi/micro/Makefile.frag',
                '/POST_MICRO_BUILD_COMMANDS=.*/',
                'POST_MICRO_BUILD_COMMANDS=true',
            );
        }

        // TODO: remove the following line when https://github.com/php/php-src/pull/14002 will be released
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/intl/config.m4', 'PHP_CXX_COMPILE_STDCXX(11', 'PHP_CXX_COMPILE_STDCXX(17');

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec(
                getenv('SPC_CMD_PREFIX_PHP_CONFIGURE') . ' ' .
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
        $vars = SystemUtil::makeEnvVarString($this->getMakeExtraVars());
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec("\$SPC_CMD_PREFIX_PHP_MAKE {$vars} cli");

        if ($this->getOption('with-upx-pack')) {
            shell()->cd(SOURCE_PATH . '/php-src/sapi/cli')
                ->exec('strip --strip-all php')
                ->exec(getenv('UPX_EXEC') . ' --best php');
        } elseif (!$this->getOption('no-strip', false)) {
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

        $enable_fake_cli = $this->getOption('with-micro-fake-cli', false) ? ' -DPHP_MICRO_FAKE_CLI' : '';
        $vars = $this->getMakeExtraVars();

        // patch fake cli for micro
        $vars['EXTRA_CFLAGS'] .= $enable_fake_cli;
        $vars = SystemUtil::makeEnvVarString($vars);

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec("\$SPC_CMD_PREFIX_PHP_MAKE {$vars} micro");

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
        $vars = SystemUtil::makeEnvVarString($this->getMakeExtraVars());
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec("\$SPC_CMD_PREFIX_PHP_MAKE {$vars} fpm");

        if ($this->getOption('with-upx-pack')) {
            shell()->cd(SOURCE_PATH . '/php-src/sapi/fpm')
                ->exec('strip --strip-all php-fpm')
                ->exec(getenv('UPX_EXEC') . ' --best php-fpm');
        } elseif (!$this->getOption('no-strip', false)) {
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
        $vars = SystemUtil::makeEnvVarString($this->getMakeExtraVars());

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec(getenv('SPC_CMD_PREFIX_PHP_MAKE') . ' INSTALL_ROOT=' . BUILD_ROOT_PATH . " {$vars} install");
    }

    private function getMakeExtraVars(): array
    {
        return [
            'EXTRA_CFLAGS' => getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS'),
            'EXTRA_LIBS' => getenv('SPC_EXTRA_LIBS') . ' ' . getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LIBS'),
            'EXTRA_LDFLAGS_PROGRAM' => getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS_PROGRAM'),
        ];
    }
}
