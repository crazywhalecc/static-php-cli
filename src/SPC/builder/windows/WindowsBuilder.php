<?php

declare(strict_types=1);

namespace SPC\builder\windows;

use SPC\builder\BuilderBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\FileSystem;
use SPC\store\SourceManager;
use SPC\store\SourcePatcher;
use SPC\util\DependencyUtil;

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

        // ---------- set necessary options ----------
        // set sdk (require visual studio 16 or 17)
        $vs = SystemUtil::findVisualStudio()['version'];
        $this->sdk_prefix = PHP_SDK_PATH . "\\phpsdk-{$vs}-x64.bat -t";

        // set zts
        $this->zts = $this->getOption('enable-zts', false);

        // set concurrency
        $this->concurrency = SystemUtil::getCpuCount();

        // make cmake toolchain
        $this->cmake_toolchain_file = SystemUtil::makeCmakeToolchainFile();
    }

    /**
     * @throws RuntimeException
     * @throws WrongUsageException
     * @throws FileSystemException
     */
    public function buildPHP(int $build_target = BUILD_TARGET_NONE): void
    {
        // ---------- Update extra-libs ----------
        $extra_libs = $this->getOption('extra-libs', '');
        $extra_libs .= (empty($extra_libs) ? '' : ' ') . implode(' ', $this->getAllStaticLibFiles());
        $this->setOption('extra-libs', $extra_libs);
        $enableCli = ($build_target & BUILD_TARGET_CLI) === BUILD_TARGET_CLI;
        $enableFpm = ($build_target & BUILD_TARGET_FPM) === BUILD_TARGET_FPM;
        $enableMicro = ($build_target & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO;
        $enableEmbed = ($build_target & BUILD_TARGET_EMBED) === BUILD_TARGET_EMBED;

        SourcePatcher::patchBeforeBuildconf($this);

        cmd()->cd(SOURCE_PATH . '\php-src')->exec("{$this->sdk_prefix} buildconf.bat");

        SourcePatcher::patchBeforeConfigure($this);

        $zts = $this->zts ? '--enable-zts=yes ' : '--enable-zts=no ';

        // with-upx-pack for phpmicro
        $makefile = FileSystem::convertPath(SOURCE_PATH . '/php-src/sapi/micro/Makefile.frag.w32');
        if ($this->getOption('with-upx-pack', false)) {
            if (!file_exists($makefile . '.originfile')) {
                copy($makefile, $makefile . '.originfile');
                FileSystem::replaceFileStr($makefile, '$(MICRO_SFX):', "_MICRO_UPX = {$this->getOption('upx-exec')} --best $(MICRO_SFX)\n$(MICRO_SFX):");
                FileSystem::replaceFileStr($makefile, '@$(_MICRO_MT)', "@$(_MICRO_MT)\n\t@$(_MICRO_UPX)");
            }
        } elseif (file_exists($makefile . '.originfile')) {
            copy($makefile . '.originfile', $makefile);
            unlink($makefile . '.originfile');
        }

        if (($logo = $this->getOption('with-micro-logo')) !== null) {
            // realpath
            $logo = realpath($logo);
            $micro_logo = '--enable-micro-logo=' . escapeshellarg($logo) . ' ';
        } else {
            $micro_logo = '';
        }

        cmd()->cd(SOURCE_PATH . '\php-src')
            ->exec(
                "{$this->sdk_prefix} configure.bat --task-args \"" .
                '--disable-all ' .
                '--disable-cgi ' .
                '--with-php-build=' . BUILD_ROOT_PATH . ' ' .
                '--with-extra-includes=' . BUILD_INCLUDE_PATH . ' ' .
                '--with-extra-libs=' . BUILD_LIB_PATH . ' ' .
                ($enableCli ? '--enable-cli=yes ' : '--enable-cli=no ') .
                ($enableMicro ? ('--enable-micro=yes ' . $micro_logo) : '--enable-micro=no ') .
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
            SourcePatcher::patchMicro(['phar']);
        }

        cmd()->cd(SOURCE_PATH . '\php-src')->exec("{$this->sdk_prefix} nmake_micro_wrapper.bat --task-args micro");

        if ($this->phar_patched) {
            SourcePatcher::patchMicro(['phar'], true);
        }

        $this->deployBinary(BUILD_TARGET_MICRO);
    }

    public function buildLibs(array $sorted_libraries): void
    {
        // search all supported libs
        $support_lib_list = [];
        $classes = FileSystem::getClassesPsr4(
            ROOT_DIR . '\src\SPC\builder\\' . osfamily2dir() . '\\library',
            'SPC\\builder\\' . osfamily2dir() . '\\library'
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

        // extract sources
        SourceManager::initSource(libs: $sorted_libraries);

        // build all libs
        foreach ($this->libs as $lib) {
            match ($lib->tryBuild($this->getOption('rebuild', false))) {
                BUILD_STATUS_OK => logger()->info('lib [' . $lib::NAME . '] build success'),
                BUILD_STATUS_ALREADY => logger()->notice('lib [' . $lib::NAME . '] already built'),
                BUILD_STATUS_FAILED => logger()->error('lib [' . $lib::NAME . '] build failed'),
                default => logger()->warning('lib [' . $lib::NAME . '] build status unknown'),
            };
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
            if (file_exists(SOURCE_PATH . '\hello.exe')) {
                @unlink(SOURCE_PATH . '\hello.exe');
            }
            file_put_contents(
                SOURCE_PATH . '\hello.exe',
                file_get_contents(BUILD_ROOT_PATH . '\bin\micro.sfx') .
                ($this->getOption('without-micro-ext-test') ? '<?php echo "[micro-test-start][micro-test-end]";' : $this->generateMicroExtTests())
            );
            chmod(SOURCE_PATH . '\hello.exe', 0755);
            [$ret, $output2] = cmd()->execWithResult(SOURCE_PATH . '\hello.exe');
            $raw_out = trim(implode('', $output2));
            $condition[0] = $ret === 0;
            $condition[1] = str_starts_with($raw_out, '[micro-test-start]') && str_ends_with($raw_out, '[micro-test-end]');
            foreach ($condition as $k => $v) {
                if (!$v) {
                    throw new RuntimeException("micro failed sanity check with condition[{$k}], ret[{$ret}], out[{$raw_out}]");
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

        // with-upx-pack for cli
        if ($this->getOption('with-upx-pack', false) && $type === BUILD_TARGET_CLI) {
            cmd()->exec($this->getOption('upx-exec') . ' --best ' . escapeshellarg($src));
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
