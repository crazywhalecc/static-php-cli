<?php

declare(strict_types=1);

namespace SPC\builder\linux;

use SPC\builder\unix\UnixBuilderBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
use SPC\store\SourcePatcher;
use SPC\toolchain\ToolchainManager;
use SPC\toolchain\ZigToolchain;
use SPC\util\GlobalEnvManager;
use SPC\util\SPCTarget;

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

        GlobalEnvManager::init();
        GlobalEnvManager::afterInit();

        // concurrency
        $this->concurrency = (int) getenv('SPC_CONCURRENCY');
        // cflags
        $this->arch_c_flags = getenv('SPC_DEFAULT_C_FLAGS');
        $this->arch_cxx_flags = getenv('SPC_DEFAULT_CXX_FLAGS');

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
        $extra_libs .= (ToolchainManager::getToolchainClass() === ZigToolchain::class ? ' -lunwind' : '');
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
        if (!$disable_jit && $this->getExt('opcache')) {
            f_putenv('SPC_COMPILER_EXTRA=-fno-sanitize=undefined');
        }
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
            'LIBS' => $mimallocLibs . SPCTarget::getRuntimeLibs(),
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
        if ($embed_type !== 'static' && SPCTarget::isStatic()) {
            throw new WrongUsageException(
                'Linux does not support loading shared libraries when linking libc statically. ' .
                'Change SPC_CMD_VAR_PHP_EMBED_TYPE to static.'
            );
        }
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

        if (!$this->getOption('no-strip', false)) {
            shell()->cd(SOURCE_PATH . '/php-src/sapi/cli')->exec('strip --strip-all php');
        }
        if ($this->getOption('with-upx-pack')) {
            shell()->cd(SOURCE_PATH . '/php-src/sapi/cli')
                ->exec(getenv('UPX_EXEC') . ' --best php');
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

        if (!$this->getOption('no-strip', false)) {
            shell()->cd(SOURCE_PATH . '/php-src/sapi/fpm')->exec('strip --strip-all php-fpm');
        }
        if ($this->getOption('with-upx-pack')) {
            shell()->cd(SOURCE_PATH . '/php-src/sapi/fpm')
                ->exec(getenv('UPX_EXEC') . ' --best php-fpm');
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
        $realLibName = 'libphp.so';
        if (preg_match('/-release\s+(\S+)/', $ldflags, $matches)) {
            $release = $matches[1];
            $realLibName = 'libphp-' . $release . '.so';
            $cwd = getcwd();
            $libphpPath = BUILD_LIB_PATH . '/libphp.so';
            $libphpRelease = BUILD_LIB_PATH . '/' . $realLibName;
            if (!file_exists($libphpRelease) && file_exists($libphpPath)) {
                rename($libphpPath, $libphpRelease);
            }
            if (file_exists($libphpRelease)) {
                chdir(BUILD_LIB_PATH);
                if (file_exists($libphpPath)) {
                    unlink($libphpPath);
                }
                symlink($realLibName, 'libphp.so');
            }
            if (is_dir(BUILD_MODULES_PATH)) {
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
            }
            chdir($cwd);
        }
        if (!$this->getOption('no-strip', false) && file_exists(BUILD_LIB_PATH . '/' . $realLibName)) {
            shell()->cd(BUILD_LIB_PATH)->exec("strip --strip-all {$realLibName}");
        }
        $this->patchPhpScripts();
    }

    private function getMakeExtraVars(): array
    {
        return [
            'EXTRA_CFLAGS' => getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS'),
            'EXTRA_LIBS' => getenv('SPC_EXTRA_LIBS') . ' ' . SPCTarget::getRuntimeLibs(),
            'EXTRA_LDFLAGS' => getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS'),
            'EXTRA_LDFLAGS_PROGRAM' => SPCTarget::isStatic() ? '-all-static -pie' : '-pie',
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
