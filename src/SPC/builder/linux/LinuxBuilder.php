<?php

declare(strict_types=1);

namespace SPC\builder\linux;

use SPC\builder\unix\UnixBuilderBase;
use SPC\exception\PatchException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
use SPC\store\SourcePatcher;
use SPC\util\GlobalEnvManager;
use SPC\util\SPCConfigUtil;
use SPC\util\SPCTarget;

class LinuxBuilder extends UnixBuilderBase
{
    /** @var bool Micro patch phar flag */
    private bool $phar_patched = false;

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
        $this->arch_ld_flags = getenv('SPC_DEFAULT_LD_FLAGS');

        // create pkgconfig and include dir (some libs cannot create them automatically)
        f_mkdir(BUILD_LIB_PATH . '/pkgconfig', recursive: true);
        f_mkdir(BUILD_INCLUDE_PATH, recursive: true);
    }

    /**
     * Build PHP from source.
     *
     * @param int $build_target Build target, use `BUILD_TARGET_*` constants
     */
    public function buildPHP(int $build_target = BUILD_TARGET_NONE): void
    {
        $cflags = $this->arch_c_flags;
        f_putenv('CFLAGS=' . $cflags);

        $this->emitPatchPoint('before-php-buildconf');
        SourcePatcher::patchBeforeBuildconf($this);

        shell()->cd(SOURCE_PATH . '/php-src')->exec(getenv('SPC_CMD_PREFIX_PHP_BUILDCONF'));

        $this->emitPatchPoint('before-php-configure');
        SourcePatcher::patchBeforeConfigure($this);

        $phpVersionID = $this->getPHPVersionID();
        $json_74 = $phpVersionID < 80000 ? '--enable-json ' : '';

        $opcache_jit = !$this->getOption('disable-opcache-jit', false);
        if ($opcache_jit && ($phpVersionID >= 80500 || $this->getExt('opcache'))) {
            // php 8.5 contains opcache extension by default,
            // if opcache_jit is enabled for 8.5 or opcache enabled,
            // we need to disable undefined behavior sanitizer.
            f_putenv('SPC_COMPILER_EXTRA=-fno-sanitize=undefined');
        }

        if ($this->getOption('enable-zts', false)) {
            $maxExecutionTimers = $phpVersionID >= 80100 ? '--enable-zend-max-execution-timers ' : '';
            $zts = '--enable-zts --disable-zend-signals ';
        } else {
            $maxExecutionTimers = '';
            $zts = '';
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

        // prepare build php envs
        // $musl_flag = SPCTarget::getLibc() === 'musl' ? ' -D__MUSL__' : ' -U__MUSL__';
        $php_configure_env = SystemUtil::makeEnvVarString([
            'CFLAGS' => getenv('SPC_CMD_VAR_PHP_CONFIGURE_CFLAGS'),
            'CPPFLAGS' => '-I' . BUILD_INCLUDE_PATH, // . ' -Dsomethinghere', // . $musl_flag,
            'LDFLAGS' => '-L' . BUILD_LIB_PATH,
            // 'LIBS' => SPCTarget::getRuntimeLibs(), // do not pass static libraries here yet, they may contain polyfills for libc functions!
        ]);

        $embed_type = getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') ?: 'static';
        if ($embed_type !== 'static' && SPCTarget::isStatic()) {
            throw new WrongUsageException(
                'Linux does not support loading shared libraries when linking libc statically. ' .
                'Change SPC_CMD_VAR_PHP_EMBED_TYPE to static.'
            );
        }

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec(
                $php_configure_env . ' ' .
                getenv('SPC_CMD_PREFIX_PHP_CONFIGURE') . ' ' .
                ($enableCli ? '--enable-cli ' : '--disable-cli ') .
                ($enableFpm ? '--enable-fpm ' . ($this->getLib('libacl') !== null ? '--with-fpm-acl ' : '') : '--disable-fpm ') .
                ($enableEmbed ? "--enable-embed={$embed_type} " : '--disable-embed ') .
                ($enableMicro ? '--enable-micro=all-static ' : '--disable-micro ') .
                $config_file_path .
                $config_file_scan_dir .
                $json_74 .
                $zts .
                $maxExecutionTimers .
                $this->makeStaticExtensionArgs() . ' '
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
     */
    protected function buildCli(): void
    {
        $vars = SystemUtil::makeEnvVarString($this->getMakeExtraVars());
        $SPC_CMD_PREFIX_PHP_MAKE = getenv('SPC_CMD_PREFIX_PHP_MAKE') ?: 'make';
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec("{$SPC_CMD_PREFIX_PHP_MAKE} {$vars} cli");

        if (!$this->getOption('no-strip', false)) {
            shell()->cd(SOURCE_PATH . '/php-src/sapi/cli')->exec('strip --strip-unneeded php');
        }
        if ($this->getOption('with-upx-pack')) {
            shell()->cd(SOURCE_PATH . '/php-src/sapi/cli')
                ->exec(getenv('UPX_EXEC') . ' --best php');
        }

        $this->deployBinary(BUILD_TARGET_CLI);
    }

    /**
     * Build phpmicro sapi
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
     */
    protected function buildFpm(): void
    {
        $vars = SystemUtil::makeEnvVarString($this->getMakeExtraVars());
        $SPC_CMD_PREFIX_PHP_MAKE = getenv('SPC_CMD_PREFIX_PHP_MAKE') ?: 'make';
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec("{$SPC_CMD_PREFIX_PHP_MAKE} {$vars} fpm");

        if (!$this->getOption('no-strip', false)) {
            shell()->cd(SOURCE_PATH . '/php-src/sapi/fpm')->exec('strip --strip-unneeded php-fpm');
        }
        if ($this->getOption('with-upx-pack')) {
            shell()->cd(SOURCE_PATH . '/php-src/sapi/fpm')
                ->exec(getenv('UPX_EXEC') . ' --best php-fpm');
        }
        $this->deployBinary(BUILD_TARGET_FPM);
    }

    /**
     * Build embed sapi
     */
    protected function buildEmbed(): void
    {
        $vars = SystemUtil::makeEnvVarString($this->getMakeExtraVars());

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec('sed -i "s|//lib|/lib|g" Makefile')
            ->exec('sed -i "s|^EXTENSION_DIR = .*|EXTENSION_DIR = /' . basename(BUILD_MODULES_PATH) . '|" Makefile')
            ->exec(getenv('SPC_CMD_PREFIX_PHP_MAKE') . ' INSTALL_ROOT=' . BUILD_ROOT_PATH . " {$vars} install");

        $ldflags = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS') ?: '';
        $libDir = BUILD_LIB_PATH;
        $modulesDir = BUILD_MODULES_PATH;
        $libphpSo = "{$libDir}/libphp.so";
        $realLibName = 'libphp.so';
        $cwd = getcwd();

        if (preg_match('/-release\s+(\S+)/', $ldflags, $matches)) {
            $release = $matches[1];
            $realLibName = "libphp-{$release}.so";
            $libphpRelease = "{$libDir}/{$realLibName}";
            if (!file_exists($libphpRelease) && file_exists($libphpSo)) {
                rename($libphpSo, $libphpRelease);
            }
            if (file_exists($libphpRelease)) {
                chdir($libDir);
                if (file_exists($libphpSo)) {
                    unlink($libphpSo);
                }
                symlink($realLibName, 'libphp.so');
                shell()->exec(sprintf(
                    'patchelf --set-soname %s %s',
                    escapeshellarg($realLibName),
                    escapeshellarg($libphpRelease)
                ));
            }
            if (is_dir($modulesDir)) {
                chdir($modulesDir);
                foreach ($this->getExts() as $ext) {
                    if (!$ext->isBuildShared()) {
                        continue;
                    }
                    $name = $ext->getName();
                    $versioned = "{$name}-{$release}.so";
                    $unversioned = "{$name}.so";
                    $src = "{$modulesDir}/{$versioned}";
                    $dst = "{$modulesDir}/{$unversioned}";
                    if (is_file($src)) {
                        rename($src, $dst);
                        shell()->exec(sprintf(
                            'patchelf --set-soname %s %s',
                            escapeshellarg($unversioned),
                            escapeshellarg($dst)
                        ));
                    }
                }
            }
            chdir($cwd);
        }

        $target = "{$libDir}/{$realLibName}";
        if (file_exists($target)) {
            [, $output] = shell()->execWithResult('readelf -d ' . escapeshellarg($target));
            $output = join("\n", $output);
            if (preg_match('/SONAME.*\[(.+)]/', $output, $sonameMatch)) {
                $currentSoname = $sonameMatch[1];
                if ($currentSoname !== basename($target)) {
                    shell()->exec(sprintf(
                        'patchelf --set-soname %s %s',
                        escapeshellarg(basename($target)),
                        escapeshellarg($target)
                    ));
                }
            }
        }

        if (getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') === 'static') {
            $AR = getenv('AR') ?: 'ar';
            f_passthru("{$AR} -t " . BUILD_LIB_PATH . "/libphp.a | grep '\\.a$' | xargs -n1 {$AR} d " . BUILD_LIB_PATH . '/libphp.a');
        }

        if (!$this->getOption('no-strip', false) && file_exists(BUILD_LIB_PATH . '/' . $realLibName)) {
            shell()->cd(BUILD_LIB_PATH)->exec("strip --strip-unneeded {$realLibName}");
        }
        $this->patchPhpScripts();
    }

    /**
     * Return extra variables for php make command.
     */
    private function getMakeExtraVars(): array
    {
        $config = (new SPCConfigUtil($this, ['libs_only_deps' => true, 'absolute_libs' => true]))->config($this->ext_list, $this->lib_list, $this->getOption('with-suggested-exts'), $this->getOption('with-suggested-libs'));
        $static = SPCTarget::isStatic() ? '-all-static' : '';
        $lib = BUILD_LIB_PATH;
        return [
            'EXTRA_CFLAGS' => getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS'),
            'EXTRA_LIBS' => $config['libs'],
            'EXTRA_LDFLAGS' => getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS'),
            'EXTRA_LDFLAGS_PROGRAM' => "-L{$lib} {$static} -pie",
        ];
    }

    /**
     * Strip micro.sfx for Linux.
     * The micro.sfx does not support UPX directly, but we can remove UPX-info segment to adapt.
     * This will also make micro.sfx with upx-packed more like a malware fore antivirus :(
     */
    private function processMicroUPX(): void
    {
        if (version_compare($this->getMicroVersion(), '0.2.0') >= 0 && !$this->getOption('no-strip', false)) {
            shell()->exec('strip --strip-unneeded ' . SOURCE_PATH . '/php-src/sapi/micro/micro.sfx');

            if ($this->getOption('with-upx-pack')) {
                // strip first
                shell()->exec(getenv('UPX_EXEC') . ' --best ' . SOURCE_PATH . '/php-src/sapi/micro/micro.sfx');
                // cut binary with readelf
                [$ret, $out] = shell()->execWithResult('readelf -l ' . SOURCE_PATH . '/php-src/sapi/micro/micro.sfx | awk \'/LOAD|GNU_STACK/ {getline; print $1, $2, $3, $4, $6, $7}\'');
                $out[1] = explode(' ', $out[1]);
                $offset = $out[1][0];
                if ($ret !== 0 || !str_starts_with($offset, '0x')) {
                    throw new PatchException('phpmicro UPX patcher', 'Cannot find offset in readelf output');
                }
                $offset = hexdec($offset);
                // remove upx extra wastes
                file_put_contents(SOURCE_PATH . '/php-src/sapi/micro/micro.sfx', substr(file_get_contents(SOURCE_PATH . '/php-src/sapi/micro/micro.sfx'), 0, $offset));
            }
        }
    }
}
