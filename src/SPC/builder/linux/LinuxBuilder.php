<?php

declare(strict_types=1);

namespace SPC\builder\linux;

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

        GlobalEnvManager::init($this);

        if (getenv('SPC_LIBC') === 'musl' && !SystemUtil::isMuslDist()) {
            $this->setOptionIfNotExist('library_path', "LIBRARY_PATH=\"/usr/local/musl/{$arch}-linux-musl/lib\"");
            $this->setOptionIfNotExist('ld_library_path', "LD_LIBRARY_PATH=\"/usr/local/musl/{$arch}-linux-musl/lib\"");
            $configure = getenv('SPC_CMD_PREFIX_PHP_CONFIGURE');
            $configure = "LD_LIBRARY_PATH=\"/usr/local/musl/{$arch}-linux-musl/lib\" " . $configure;
            GlobalEnvManager::putenv("SPC_CMD_PREFIX_PHP_CONFIGURE={$configure}");

            if (!file_exists("/usr/local/musl/{$arch}-linux-musl/lib/libc.a")) {
                throw new WrongUsageException('You are building with musl-libc target in glibc distro, but musl-toolchain is not installed, please install musl-toolchain first. (You can use `doctor` command to install it)');
            }
        }

        // concurrency
        $this->concurrency = intval(getenv('SPC_CONCURRENCY'));
        // cflags
        $this->arch_c_flags = getenv('SPC_DEFAULT_C_FLAGS');
        $this->arch_cxx_flags = getenv('SPC_DEFAULT_CXX_FLAGS');

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
     * Build PHP from source.
     *
     * @param  int                 $build_target Build target, use `BUILD_TARGET_*` constants
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
        f_putenv('CFLAGS=' . $cflags);

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

        $config_file_path = $this->getOption('with-config-file-path', false) ?
            ('--with-config-file-path=' . $this->getOption('with-config-file-path') . ' ') : '';
        $config_file_scan_dir = $this->getOption('with-config-file-scan-dir', false) ?
            ('--with-config-file-scan-dir=' . $this->getOption('with-config-file-scan-dir') . ' ') : '';

        $enableCli = ($build_target & BUILD_TARGET_CLI) === BUILD_TARGET_CLI;
        $enableFpm = ($build_target & BUILD_TARGET_FPM) === BUILD_TARGET_FPM;
        $enableMicro = ($build_target & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO;
        $enableEmbed = ($build_target & BUILD_TARGET_EMBED) === BUILD_TARGET_EMBED;
        $enableFrankenphp = ($build_target & BUILD_TARGET_FRANKENPHP) === BUILD_TARGET_FRANKENPHP;

        $mimallocLibs = $this->getLib('mimalloc') !== null ? BUILD_LIB_PATH . '/mimalloc.o ' : '';
        // prepare build php envs
        $envs_build_php = SystemUtil::makeEnvVarString([
            'CFLAGS' => getenv('SPC_CMD_VAR_PHP_CONFIGURE_CFLAGS'),
            'CPPFLAGS' => getenv('SPC_CMD_VAR_PHP_CONFIGURE_CPPFLAGS'),
            'LDFLAGS' => getenv('SPC_CMD_VAR_PHP_CONFIGURE_LDFLAGS'),
            'LIBS' => $mimallocLibs . getenv('SPC_CMD_VAR_PHP_CONFIGURE_LIBS'),
        ]);

        // process micro upx patch if micro sapi enabled
        if ($enableMicro) {
            if (version_compare($this->getMicroVersion(), '0.2.0') < 0) {
                // for phpmicro 0.1.x
                $this->processMicroUPXLegacy();
            }
            // micro latest needs do strip and upx pack later (strip, upx, cut binary manually supported)
        }

        $embed_type = getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') ?: 'static';
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec(
                getenv('SPC_CMD_PREFIX_PHP_CONFIGURE') . ' ' .
                ($enableCli ? '--enable-cli ' : '--disable-cli ') .
                ($enableFpm ? '--enable-fpm ' . ($this->getLib('libacl') !== null ? '--with-fpm-acl ' : '') : '--disable-fpm ') .
                ($enableEmbed ? "--enable-embed={$embed_type} " : '--disable-embed ') .
                ($enableMicro ? '--enable-micro=all-static ' : '--disable-micro ') .
                $config_file_path .
                $config_file_scan_dir .
                $disable_jit .
                $json_74 .
                $zts .
                $maxExecutionTimers .
                $this->makeStaticExtensionArgs() .
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
        if ($enableFrankenphp) {
            logger()->info('building frankenphp');
            $this->buildFrankenphp();
        }
    }

    public function testPHP(int $build_target = BUILD_TARGET_NONE)
    {
        $this->emitPatchPoint('before-sanity-check');
        $this->sanityCheck($build_target);
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
        $SPC_CMD_PREFIX_PHP_MAKE = getenv('SPC_CMD_PREFIX_PHP_MAKE') ?: 'make';
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec("{$SPC_CMD_PREFIX_PHP_MAKE} {$vars} cli");

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
            SourcePatcher::patchMicroPhar($this->getPHPVersionID());
        }

        $enable_fake_cli = $this->getOption('with-micro-fake-cli', false) ? ' -DPHP_MICRO_FAKE_CLI' : '';
        $vars = $this->getMakeExtraVars();

        // patch fake cli for micro
        $vars['EXTRA_CFLAGS'] .= $enable_fake_cli;
        $vars = SystemUtil::makeEnvVarString($vars);
        $SPC_CMD_PREFIX_PHP_MAKE = getenv('SPC_CMD_PREFIX_PHP_MAKE') ?: 'make';

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec("{$SPC_CMD_PREFIX_PHP_MAKE} {$vars} micro");

        $this->processMicroUPX();

        $this->deployBinary(BUILD_TARGET_MICRO);

        if ($this->phar_patched) {
            SourcePatcher::unpatchMicroPhar();
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
        $SPC_CMD_PREFIX_PHP_MAKE = getenv('SPC_CMD_PREFIX_PHP_MAKE') ?: 'make';
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec("{$SPC_CMD_PREFIX_PHP_MAKE} {$vars} fpm");

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
            ->exec('sed -i "s|^EXTENSION_DIR = .*|EXTENSION_DIR = /' . basename(BUILD_MODULES_PATH) . '|" Makefile')
            ->exec(getenv('SPC_CMD_PREFIX_PHP_MAKE') . ' INSTALL_ROOT=' . BUILD_ROOT_PATH . " {$vars} install");

        $ldflags = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS');
        if (preg_match('/-release\s+(\S+)/', $ldflags, $matches)) {
            $release = $matches[1];
            $realLibName = 'libphp-' . $release . '.so';
            $realLib = BUILD_LIB_PATH . '/' . $realLibName;
            rename(BUILD_LIB_PATH . '/libphp.so', $realLib);
            $cwd = getcwd();
            chdir(BUILD_LIB_PATH);
            symlink($realLibName, 'libphp.so');
            chdir(BUILD_MODULES_PATH);
            foreach ($this->getExts() as $ext) {
                if (!$ext->isBuildShared()) {
                    continue;
                }
                $name = $ext->getName();
                $versioned = "{$name}-{$release}.so";
                $unversioned = "{$name}.so";
                if (is_file(BUILD_MODULES_PATH . "/{$versioned}")) {
                    rename(BUILD_MODULES_PATH . "/{$versioned}", BUILD_MODULES_PATH . "/{$unversioned}");
                    shell()->cd(BUILD_MODULES_PATH)
                        ->exec(sprintf(
                            'patchelf --set-soname %s %s',
                            escapeshellarg($unversioned),
                            escapeshellarg($unversioned)
                        ));
                }
            }
            chdir($cwd);
        }
        $this->patchPhpScripts();
    }

    private function getMakeExtraVars(): array
    {
        return [
            'EXTRA_CFLAGS' => getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS'),
            'EXTRA_LIBS' => getenv('SPC_EXTRA_LIBS') . ' ' . getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LIBS'),
            'EXTRA_LDFLAGS' => getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS'),
            'EXTRA_LDFLAGS_PROGRAM' => getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS_PROGRAM'),
        ];
    }

    /**
     * Apply option --no-strip and --with-upx-pack for micro sapi (only for phpmicro 0.1.x)
     *
     * @throws FileSystemException
     */
    private function processMicroUPXLegacy(): void
    {
        // upx pack and strip for micro
        // but always restore Makefile.frag.bak first
        if (file_exists(SOURCE_PATH . '/php-src/sapi/micro/Makefile.frag.bak')) {
            copy(SOURCE_PATH . '/php-src/sapi/micro/Makefile.frag.bak', SOURCE_PATH . '/php-src/sapi/micro/Makefile.frag');
        }
        if ($this->getOption('with-upx-pack', false)) {
            // judge $(MAKE) micro_2s_objs SFX_FILESIZE=`$(STAT_SIZE) $(SAPI_MICRO_PATH)` count
            // if 2, replace src/globals/extra/micro-triple-Makefile.frag file content
            if (substr_count(FileSystem::readFile(SOURCE_PATH . '/php-src/sapi/micro/Makefile.frag'), '$(MAKE) micro_2s_objs SFX_FILESIZE=`$(STAT_SIZE) $(SAPI_MICRO_PATH)`') === 2) {
                // bak first
                copy(SOURCE_PATH . '/php-src/sapi/micro/Makefile.frag', SOURCE_PATH . '/php-src/sapi/micro/Makefile.frag.bak');
                // replace Makefile.frag content
                FileSystem::writeFile(SOURCE_PATH . '/php-src/sapi/micro/Makefile.frag', FileSystem::readFile(ROOT_DIR . '/src/globals/extra/micro-triple-Makefile.frag'));
            }
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
    }

    private function processMicroUPX(): void
    {
        if (version_compare($this->getMicroVersion(), '0.2.0') >= 0 && !$this->getOption('no-strip', false)) {
            shell()->exec('strip --strip-all ' . SOURCE_PATH . '/php-src/sapi/micro/micro.sfx');

            if ($this->getOption('with-upx-pack')) {
                // strip first
                shell()->exec(getenv('UPX_EXEC') . ' --best ' . SOURCE_PATH . '/php-src/sapi/micro/micro.sfx');
                // cut binary with readelf
                [$ret, $out] = shell()->execWithResult('readelf -l ' . SOURCE_PATH . '/php-src/sapi/micro/micro.sfx | awk \'/LOAD|GNU_STACK/ {getline; print $1, $2, $3, $4, $6, $7}\'');
                $out[1] = explode(' ', $out[1]);
                $offset = $out[1][0];
                if ($ret !== 0 || !str_starts_with($offset, '0x')) {
                    throw new RuntimeException('Cannot find offset in readelf output');
                }
                $offset = hexdec($offset);
                // remove upx extra wastes
                file_put_contents(SOURCE_PATH . '/php-src/sapi/micro/micro.sfx', substr(file_get_contents(SOURCE_PATH . '/php-src/sapi/micro/micro.sfx'), 0, $offset));
            }
        }
    }
}
