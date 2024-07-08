<?php

declare(strict_types=1);

namespace SPC\builder\windows;

use SPC\builder\BuilderBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\FileSystem;
use SPC\store\SourcePatcher;
use SPC\util\DependencyUtil;
use SPC\util\GlobalEnvManager;

class WindowsBuilder extends BuilderBase
{
    /** @var string cmake toolchain file */
    public string $cmake_toolchain_file;

    public string $sdk_prefix;

    private bool $zts;

    /** @var bool Micro patch phar flag */
    private bool $phar_patched = false;

    /**
     * @throws FileSystemException
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;

        GlobalEnvManager::init($this);

        // ---------- set necessary options ----------
        // set sdk (require visual studio 16 or 17)
        $vs = SystemUtil::findVisualStudio()['version'];
        $this->sdk_prefix = PHP_SDK_PATH . "\\phpsdk-{$vs}-x64.bat -t";

        // set zts
        $this->zts = $this->getOption('enable-zts', false);

        // set concurrency
        $this->concurrency = intval(getenv('SPC_CONCURRENCY'));

        // make cmake toolchain
        $this->cmake_toolchain_file = SystemUtil::makeCmakeToolchainFile();

        f_mkdir(BUILD_INCLUDE_PATH, recursive: true);
        f_mkdir(BUILD_LIB_PATH, recursive: true);
    }

    /**
     * @throws RuntimeException
     * @throws WrongUsageException
     * @throws FileSystemException
     */
    public function buildPHP(int $build_target = BUILD_TARGET_NONE): void
    {
        // ---------- Update extra-libs ----------
        $extra_libs = getenv('SPC_EXTRA_LIBS') ?: '';
        $extra_libs .= (empty($extra_libs) ? '' : ' ') . implode(' ', $this->getAllStaticLibFiles());
        f_putenv('SPC_EXTRA_LIBS=' . $extra_libs);

        $enableCli = ($build_target & BUILD_TARGET_CLI) === BUILD_TARGET_CLI;
        $enableFpm = ($build_target & BUILD_TARGET_FPM) === BUILD_TARGET_FPM;
        $enableMicro = ($build_target & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO;
        $enableEmbed = ($build_target & BUILD_TARGET_EMBED) === BUILD_TARGET_EMBED;

        SourcePatcher::patchBeforeBuildconf($this);

        cmd()->cd(SOURCE_PATH . '\php-src')->exec("{$this->sdk_prefix} buildconf.bat");

        SourcePatcher::patchBeforeConfigure($this);

        $zts = $this->zts ? '--enable-zts=yes ' : '--enable-zts=no ';

        // with-upx-pack for phpmicro
        if ($enableMicro && version_compare($this->getMicroVersion(), '0.2.0') < 0) {
            $makefile = FileSystem::convertPath(SOURCE_PATH . '/php-src/sapi/micro/Makefile.frag.w32');
            if ($this->getOption('with-upx-pack', false)) {
                if (!file_exists($makefile . '.originfile')) {
                    copy($makefile, $makefile . '.originfile');
                    FileSystem::replaceFileStr($makefile, '$(MICRO_SFX):', '_MICRO_UPX = ' . getenv('UPX_EXEC') . " --best $(MICRO_SFX)\n$(MICRO_SFX):");
                    FileSystem::replaceFileStr($makefile, '@$(_MICRO_MT)', "@$(_MICRO_MT)\n\t@$(_MICRO_UPX)");
                }
            } elseif (file_exists($makefile . '.originfile')) {
                copy($makefile . '.originfile', $makefile);
                unlink($makefile . '.originfile');
            }
        }

        if (($logo = $this->getOption('with-micro-logo')) !== null) {
            // realpath
            $logo = realpath($logo);
            $micro_logo = '--enable-micro-logo=' . $logo . ' ';
        } else {
            $micro_logo = '';
        }

        $micro_w32 = $this->getOption('enable-micro-win32') ? ' --enable-micro-win32=yes' : '';

        cmd()->cd(SOURCE_PATH . '\php-src')
            ->exec(
                "{$this->sdk_prefix} configure.bat --task-args \"" .
                '--disable-all ' .
                '--disable-cgi ' .
                '--with-php-build=' . BUILD_ROOT_PATH . ' ' .
                '--with-extra-includes=' . BUILD_INCLUDE_PATH . ' ' .
                '--with-extra-libs=' . BUILD_LIB_PATH . ' ' .
                ($enableCli ? '--enable-cli=yes ' : '--enable-cli=no ') .
                ($enableMicro ? ('--enable-micro=yes ' . $micro_logo . $micro_w32) : '--enable-micro=no ') .
                ($enableEmbed ? '--enable-embed=yes ' : '--enable-embed=no ') .
                "{$this->makeExtensionArgs()} " .
                $zts .
                '"'
            );

        SourcePatcher::patchBeforeMake($this);

        $this->cleanMake();

        if ($enableCli) {
            logger()->info('building cli');
            $this->buildCli();
        }
        if ($enableFpm) {
            logger()->warning('Windows does not support fpm SAPI, I will skip it.');
        }
        if ($enableMicro) {
            logger()->info('building micro');
            $this->buildMicro();

            SourcePatcher::unpatchMicroWin32();
        }
        if ($enableEmbed) {
            logger()->warning('Windows does not currently support embed SAPI.');
            // logger()->info('building embed');
            $this->buildEmbed();
        }

        $this->sanityCheck($build_target);
    }

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function buildCli(): void
    {
        SourcePatcher::patchWindowsCLITarget();

        // add nmake wrapper
        FileSystem::writeFile(SOURCE_PATH . '\php-src\nmake_cli_wrapper.bat', "nmake /nologo LIBS_CLI=\"{$this->getOption('extra-libs')} ws2_32.lib shell32.lib\" EXTRA_LD_FLAGS_PROGRAM= %*");

        cmd()->cd(SOURCE_PATH . '\php-src')->exec("{$this->sdk_prefix} nmake_cli_wrapper.bat --task-args php.exe");

        $this->deployBinary(BUILD_TARGET_CLI);
    }

    public function buildEmbed(): void
    {
        // TODO: add embed support for windows
        /*
        FileSystem::writeFile(SOURCE_PATH . '\php-src\nmake_embed_wrapper.bat', 'nmake /nologo %*');

        cmd()->cd(SOURCE_PATH . '\php-src')
            ->exec("{$this->sdk_prefix} nmake_embed_wrapper.bat --task-args php8embed.lib");
        */
    }

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function buildMicro(): void
    {
        // workaround for fiber (originally from https://github.com/dixyes/lwmbs/blob/master/windows/MicroBuild.php)
        $makefile = FileSystem::readFile(SOURCE_PATH . '\php-src\Makefile');
        if ($this->getPHPVersionID() >= 80200 && str_contains($makefile, 'FIBER_ASM_ARCH')) {
            $makefile .= "\r\n" . '$(MICRO_SFX): $(BUILD_DIR)\Zend\jump_$(FIBER_ASM_ARCH)_ms_pe_masm.obj $(BUILD_DIR)\Zend\make_$(FIBER_ASM_ARCH)_ms_pe_masm.obj' . "\r\n\r\n";
        } elseif ($this->getPHPVersionID() >= 80400 && str_contains($makefile, 'FIBER_ASM_ABI')) {
            $makefile .= "\r\n" . '$(MICRO_SFX): $(BUILD_DIR)\Zend\jump_$(FIBER_ASM_ABI).obj $(BUILD_DIR)\Zend\make_$(FIBER_ASM_ABI).obj' . "\r\n\r\n";
        }
        FileSystem::writeFile(SOURCE_PATH . '\php-src\Makefile', $makefile);

        // add nmake wrapper
        $fake_cli = $this->getOption('with-micro-fake-cli', false) ? ' /DPHP_MICRO_FAKE_CLI" ' : '';
        $wrapper = "nmake /nologo LIBS_MICRO=\"{$this->getOption('extra-libs')} ws2_32.lib shell32.lib\" CFLAGS_MICRO=\"/DZEND_ENABLE_STATIC_TSRMLS_CACHE=1{$fake_cli}\" %*";
        FileSystem::writeFile(SOURCE_PATH . '\php-src\nmake_micro_wrapper.bat', $wrapper);

        // phar patch for micro
        if ($this->getExt('phar')) {
            $this->phar_patched = true;
            SourcePatcher::patchMicroPhar($this->getPHPVersionID());
        }

        try {
            cmd()->cd(SOURCE_PATH . '\php-src')->exec("{$this->sdk_prefix} nmake_micro_wrapper.bat --task-args micro");
        } finally {
            if ($this->phar_patched) {
                SourcePatcher::unpatchMicroPhar();
            }
        }

        $this->deployBinary(BUILD_TARGET_MICRO);
    }

    public function proveLibs(array $sorted_libraries): void
    {
        // search all supported libs
        $support_lib_list = [];
        $classes = FileSystem::getClassesPsr4(
            ROOT_DIR . '\src\SPC\builder\\' . osfamily2dir() . '\library',
            'SPC\builder\\' . osfamily2dir() . '\library'
        );
        foreach ($classes as $class) {
            if (defined($class . '::NAME') && $class::NAME !== 'unknown' && Config::getLib($class::NAME) !== null) {
                $support_lib_list[$class::NAME] = $class;
            }
        }

        // if no libs specified, compile all supported libs
        if ($sorted_libraries === [] && $this->isLibsOnly()) {
            $libraries = array_keys($support_lib_list);
            $sorted_libraries = DependencyUtil::getLibs($libraries);
        }

        // add lib object for builder
        foreach ($sorted_libraries as $library) {
            // if some libs are not supported (but in config "lib.json", throw exception)
            if (!isset($support_lib_list[$library])) {
                throw new WrongUsageException('library [' . $library . '] is in the lib.json list but not supported to compile, but in the future I will support it!');
            }
            $lib = new ($support_lib_list[$library])($this);
            $this->addLib($lib);
        }

        // calculate and check dependencies
        foreach ($this->libs as $lib) {
            $lib->calcDependency();
        }
    }

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function cleanMake(): void
    {
        FileSystem::writeFile(SOURCE_PATH . '\php-src\nmake_clean_wrapper.bat', 'nmake /nologo %*');
        cmd()->cd(SOURCE_PATH . '\php-src')->exec("{$this->sdk_prefix} nmake_clean_wrapper.bat --task-args \"clean\"");
    }

    /**
     * Run extension and PHP cli and micro check
     *
     * @throws RuntimeException
     */
    public function sanityCheck(mixed $build_target): void
    {
        // remove all .dll from `buildroot/bin/`
        logger()->debug('Removing all .dll files from buildroot/bin/');
        $dlls = glob(BUILD_BIN_PATH . '\*.dll');
        foreach ($dlls as $dll) {
            @unlink($dll);
        }
        // sanity check for php-cli
        if (($build_target & BUILD_TARGET_CLI) === BUILD_TARGET_CLI) {
            logger()->info('running cli sanity check');
            [$ret, $output] = cmd()->execWithResult(BUILD_ROOT_PATH . '\bin\php.exe -r "echo \"hello\";"');
            if ($ret !== 0 || trim(implode('', $output)) !== 'hello') {
                throw new RuntimeException('cli failed sanity check');
            }

            foreach ($this->exts as $ext) {
                logger()->debug('testing ext: ' . $ext->getName());
                $ext->runCliCheckWindows();
            }
        }

        // sanity check for phpmicro
        if (($build_target & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO) {
            $test_task = $this->getMicroTestTasks();
            foreach ($test_task as $task_name => $task) {
                $test_file = SOURCE_PATH . '/' . $task_name . '.exe';
                if (file_exists($test_file)) {
                    @unlink($test_file);
                }
                file_put_contents($test_file, file_get_contents(BUILD_ROOT_PATH . '\bin\micro.sfx') . $task['content']);
                chmod($test_file, 0755);
                [$ret, $out] = cmd()->execWithResult($test_file);
                foreach ($task['conditions'] as $condition => $closure) {
                    if (!$closure($ret, $out)) {
                        $raw_out = trim(implode('', $out));
                        throw new RuntimeException("micro failed sanity check: {$task_name}, condition [{$condition}], ret[{$ret}], out[{$raw_out}]");
                    }
                }
            }
        }
    }

    /**
     * 将编译好的二进制文件发布到 buildroot
     *
     * @param  int                 $type 发布类型
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function deployBinary(int $type): bool
    {
        $ts = $this->zts ? '_TS' : '';
        $src = match ($type) {
            BUILD_TARGET_CLI => SOURCE_PATH . "\\php-src\\x64\\Release{$ts}\\php.exe",
            BUILD_TARGET_MICRO => SOURCE_PATH . "\\php-src\\x64\\Release{$ts}\\micro.sfx",
            default => throw new RuntimeException('Deployment does not accept type ' . $type),
        };

        // with-upx-pack for cli and micro
        if ($this->getOption('with-upx-pack', false)) {
            if ($type === BUILD_TARGET_CLI || ($type === BUILD_TARGET_MICRO && version_compare($this->getMicroVersion(), '0.2.0') >= 0)) {
                cmd()->exec(getenv('UPX_EXEC') . ' --best ' . escapeshellarg($src));
            }
        }

        logger()->info('Deploying ' . $this->getBuildTypeName($type) . ' file');
        FileSystem::createDir(BUILD_ROOT_PATH . '\bin');

        cmd()->exec('copy ' . escapeshellarg($src) . ' ' . escapeshellarg(BUILD_ROOT_PATH . '\bin\\'));
        return true;
    }

    /**
     * @throws WrongUsageException
     * @throws FileSystemException
     */
    public function getAllStaticLibFiles(): array
    {
        $libs = [];

        // reorder libs
        foreach ($this->libs as $lib) {
            foreach ($lib->getDependencies() as $dep) {
                $libs[] = $dep;
            }
            $libs[] = $lib;
        }

        $libFiles = [];
        $libNames = [];
        // merge libs
        foreach ($libs as $lib) {
            if (!in_array($lib::NAME, $libNames, true)) {
                $libNames[] = $lib::NAME;
                array_unshift($libFiles, ...$lib->getStaticLibs());
            }
        }
        return $libFiles;
    }

    /**
     * Generate command wrapper prefix for php-sdk internal commands.
     *
     * @param  string $internal_cmd command in php-sdk-tools\bin
     * @return string Example: C:\php-sdk-tools\phpsdk-vs17-x64.bat -t source\cmake_wrapper.bat --task-args
     */
    public function makeSimpleWrapper(string $internal_cmd): string
    {
        $wrapper_bat = SOURCE_PATH . '\\' . crc32($internal_cmd) . '_wrapper.bat';
        if (!file_exists($wrapper_bat)) {
            file_put_contents($wrapper_bat, $internal_cmd . ' %*');
        }
        return "{$this->sdk_prefix} {$wrapper_bat} --task-args";
    }
}
