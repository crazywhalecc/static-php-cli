<?php

declare(strict_types=1);

namespace SPC\builder\macos;

use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\builder\unix\UnixBuilderBase;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
use SPC\store\SourcePatcher;
use SPC\util\GlobalEnvManager;
use SPC\util\SPCConfigUtil;

class MacOSBuilder extends UnixBuilderBase
{
    /** @var bool Micro patch phar flag */
    private bool $phar_patched = false;

    public function __construct(array $options = [])
    {
        $this->options = $options;

        // apply global environment variables
        GlobalEnvManager::init();
        GlobalEnvManager::afterInit();

        // ---------- set necessary compile vars ----------
        // concurrency
        $this->concurrency = intval(getenv('SPC_CONCURRENCY'));
        // cflags
        $this->arch_c_flags = getenv('SPC_DEFAULT_C_FLAGS');
        $this->arch_cxx_flags = getenv('SPC_DEFAULT_CXX_FLAGS');
        $this->arch_ld_flags = getenv('SPC_DEFAULT_LD_FLAGS');

        // create pkgconfig and include dir (some libs cannot create them automatically)
        f_mkdir(BUILD_LIB_PATH . '/pkgconfig', recursive: true);
        f_mkdir(BUILD_INCLUDE_PATH, recursive: true);
    }

    /**
     * Get dynamically linked macOS frameworks
     *
     * @param bool $asString If true, return as string
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

        foreach ($this->exts as $ext) {
            array_push($frameworks, ...$ext->getFrameworks());
        }

        if ($asString) {
            return implode(' ', array_map(fn ($x) => "-framework {$x}", $frameworks));
        }
        return $frameworks;
    }

    /**
     * Just start to build statically linked php binary
     *
     * @param int $build_target build target
     */
    public function buildPHP(int $build_target = BUILD_TARGET_NONE): void
    {
        $this->emitPatchPoint('before-php-buildconf');
        SourcePatcher::patchBeforeBuildconf($this);

        shell()->cd(SOURCE_PATH . '/php-src')->exec(getenv('SPC_CMD_PREFIX_PHP_BUILDCONF'));

        $this->emitPatchPoint('before-php-configure');
        SourcePatcher::patchBeforeConfigure($this);

        $phpVersionID = $this->getPHPVersionID();
        $json_74 = $phpVersionID < 80000 ? '--enable-json ' : '';
        $zts = $this->getOption('enable-zts', false) ? '--enable-zts --disable-zend-signals ' : '';

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
        $envs_build_php = SystemUtil::makeEnvVarString([
            'CFLAGS' => getenv('SPC_CMD_VAR_PHP_CONFIGURE_CFLAGS'),
            'CPPFLAGS' => '-I' . BUILD_INCLUDE_PATH,
            'LDFLAGS' => '-L' . BUILD_LIB_PATH,
        ]);

        if ($this->getLib('postgresql')) {
            shell()
                ->cd(SOURCE_PATH . '/php-src')
                ->exec(
                    'sed -i.backup "s/ac_cv_func_explicit_bzero\" = xyes/ac_cv_func_explicit_bzero\" = x_fake_yes/" ./configure'
                );
        }

        $embed_type = getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') ?: 'static';
        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec(
                getenv('SPC_CMD_PREFIX_PHP_CONFIGURE') . ' ' .
                ($enableCli ? '--enable-cli ' : '--disable-cli ') .
                ($enableFpm ? '--enable-fpm ' : '--disable-fpm ') .
                ($enableEmbed ? "--enable-embed={$embed_type} " : '--disable-embed ') .
                ($enableMicro ? '--enable-micro ' : '--disable-micro ') .
                $config_file_path .
                $config_file_scan_dir .
                $json_74 .
                $zts .
                $this->makeStaticExtensionArgs() . ' ' .
                $envs_build_php
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

        $shell = shell()->cd(SOURCE_PATH . '/php-src');
        $SPC_CMD_PREFIX_PHP_MAKE = getenv('SPC_CMD_PREFIX_PHP_MAKE') ?: 'make';
        $shell->exec("{$SPC_CMD_PREFIX_PHP_MAKE} {$vars} cli");
        if (!$this->getOption('no-strip', false)) {
            $shell->exec('dsymutil -f sapi/cli/php')->exec('strip -S sapi/cli/php');
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

        $shell = shell()->cd(SOURCE_PATH . '/php-src');
        // build
        $shell->exec(getenv('SPC_CMD_PREFIX_PHP_MAKE') . " {$vars} micro");
        // strip
        if (!$this->getOption('no-strip', false)) {
            $shell->exec('dsymutil -f sapi/micro/micro.sfx')->exec('strip -S sapi/micro/micro.sfx');
        }

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

        $shell = shell()->cd(SOURCE_PATH . '/php-src');
        $shell->exec(getenv('SPC_CMD_PREFIX_PHP_MAKE') . " {$vars} fpm");
        if (!$this->getOption('no-strip', false)) {
            $shell->exec('dsymutil -f sapi/fpm/php-fpm')->exec('strip -S sapi/fpm/php-fpm');
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
            ->exec(getenv('SPC_CMD_PREFIX_PHP_MAKE') . ' INSTALL_ROOT=' . BUILD_ROOT_PATH . " {$vars} install");

        if (getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') === 'static') {
            $AR = getenv('AR') ?: 'ar';
            f_passthru("{$AR} -t " . BUILD_LIB_PATH . "/libphp.a | grep '\\.a$' | xargs -n1 {$AR} d " . BUILD_LIB_PATH . '/libphp.a');
        }
        $this->patchPhpScripts();
    }

    private function getMakeExtraVars(): array
    {
        $config = (new SPCConfigUtil($this, ['libs_only_deps' => true]))->config($this->ext_list, $this->lib_list, $this->getOption('with-suggested-exts'), $this->getOption('with-suggested-libs'));
        return [
            'EXTRA_CFLAGS' => getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS'),
            'EXTRA_LDFLAGS_PROGRAM' => '-L' . BUILD_LIB_PATH,
            'EXTRA_LIBS' => $config['libs'],
        ];
    }
}
