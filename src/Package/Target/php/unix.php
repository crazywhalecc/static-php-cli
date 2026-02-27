<?php

declare(strict_types=1);

namespace Package\Target\php;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Stage;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\PatchException;
use StaticPHP\Exception\SPCException;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Util\DirDiff;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use StaticPHP\Util\SourcePatcher;
use StaticPHP\Util\SPCConfigUtil;
use StaticPHP\Util\System\UnixUtil;
use StaticPHP\Util\V2CompatLayer;
use ZM\Logger\ConsoleColor;

trait unix
{
    #[BeforeStage('php', [self::class, 'buildconfForUnix'], 'php')]
    #[PatchDescription('Patch configure.ac for musl and musl-toolchain')]
    #[PatchDescription('Let php m4 tools use static pkg-config')]
    public function patchBeforeBuildconf(TargetPackage $package): void
    {
        // patch configure.ac for musl and musl-toolchain
        $musl = SystemTarget::getTargetOS() === 'Linux' && SystemTarget::getLibc() === 'musl';
        FileSystem::backupFile(SOURCE_PATH . '/php-src/configure.ac');
        FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/configure.ac',
            'if command -v ldd >/dev/null && ldd --version 2>&1 | grep ^musl >/dev/null 2>&1',
            'if ' . ($musl ? 'true' : 'false')
        );

        // let php m4 tools use static pkg-config
        FileSystem::replaceFileStr("{$package->getSourceDir()}/build/php.m4", 'PKG_CHECK_MODULES(', 'PKG_CHECK_MODULES_STATIC(');
    }

    #[Stage]
    public function buildconfForUnix(TargetPackage $package): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('./buildconf'));
        V2CompatLayer::emitPatchPoint('before-php-buildconf');
        shell()->cd($package->getSourceDir())->exec(getenv('SPC_CMD_PREFIX_PHP_BUILDCONF'));
    }

    #[Stage]
    public function configureForUnix(TargetPackage $package, PackageInstaller $installer): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('./configure'));
        V2CompatLayer::emitPatchPoint('before-php-configure');
        $cmd = getenv('SPC_CMD_PREFIX_PHP_CONFIGURE');

        $args = [];
        $version_id = self::getPHPVersionID();

        // disable undefined behavior sanitizer when opcache JIT is enabled (Linux only)
        if (SystemTarget::getTargetOS() === 'Linux' && !$package->getBuildOption('disable-opcache-jit', false)) {
            if ($version_id >= 80500 || $installer->isPackageResolved('ext-opcache')) {
                f_putenv('SPC_COMPILER_EXTRA=-fno-sanitize=undefined');
            }
        }
        // PHP JSON extension is built-in since PHP 8.0
        if ($version_id < 80000) {
            $args[] = '--enable-json';
        }
        // zts
        if ($package->getBuildOption('enable-zts', false)) {
            $args[] = '--enable-zts --disable-zend-signals';
            if ($version_id >= 80100 && SystemTarget::getTargetOS() === 'Linux') {
                $args[] = '--enable-zend-max-execution-timers';
            }
        }
        // config-file-path and config-file-scan-dir
        if ($option = $package->getBuildOption('with-config-file-path', false)) {
            $args[] = "--with-config-file-path={$option}";
        }
        if ($option = $package->getBuildOption('with-config-file-scan-dir', false)) {
            $args[] = "--with-config-file-scan-dir={$option}";
        }
        // perform enable cli options
        $args[] = $installer->isPackageResolved('php-cli') ? '--enable-cli' : '--disable-cli';
        $args[] = $installer->isPackageResolved('php-fpm')
            ? '--enable-fpm' . ($installer->isPackageResolved('libacl') ? ' --with-fpm-acl' : '')
            : '--disable-fpm';
        $args[] = $installer->isPackageResolved('php-micro') ? match (SystemTarget::getTargetOS()) {
            'Linux' => '--enable-micro=all-static',
            default => '--enable-micro',
        } : null;
        $args[] = $installer->isPackageResolved('php-cgi') ? '--enable-cgi' : '--disable-cgi';
        $embed_type = getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') ?: 'static';
        $args[] = $installer->isPackageResolved('php-embed') ? "--enable-embed={$embed_type}" : '--disable-embed';
        $args[] = getenv('SPC_EXTRA_PHP_VARS') ?: null;
        $args = implode(' ', array_filter($args));

        $static_extension_str = $this->makeStaticExtensionString($installer);

        // run ./configure with args
        $this->seekPhpSrcLogFileOnException(fn () => shell()->cd($package->getSourceDir())->setEnv([
            'CFLAGS' => getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS'),
            'CPPFLAGS' => "-I{$package->getIncludeDir()}",
            'LDFLAGS' => "-L{$package->getLibDir()} " . getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS'),
        ])->exec("{$cmd} {$args} {$static_extension_str}"), $package->getSourceDir());
    }

    #[BeforeStage('php', [self::class, 'makeForUnix'], 'php')]
    #[PatchDescription('Patch TSRM.h to fix musl TLS symbol visibility for non-static builds')]
    public function beforeMakeUnix(ToolchainInterface $toolchain): void
    {
        if (!$toolchain->isStatic() && SystemTarget::getLibc() === 'musl') {
            // we need to patch the symbol to global visibility, otherwise extensions with `initial-exec` TLS model will fail to load
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/TSRM/TSRM.h',
                '#define TSRMLS_MAIN_CACHE_DEFINE() TSRM_TLS void *TSRMLS_CACHE TSRM_TLS_MODEL_ATTR = NULL;',
                '#define TSRMLS_MAIN_CACHE_DEFINE() TSRM_TLS __attribute__((visibility("default"))) void *TSRMLS_CACHE TSRM_TLS_MODEL_ATTR = NULL;',
            );
        } else {
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/TSRM/TSRM.h',
                '#define TSRMLS_MAIN_CACHE_DEFINE() TSRM_TLS __attribute__((visibility("default"))) void *TSRMLS_CACHE TSRM_TLS_MODEL_ATTR = NULL;',
                '#define TSRMLS_MAIN_CACHE_DEFINE() TSRM_TLS void *TSRMLS_CACHE TSRM_TLS_MODEL_ATTR = NULL;',
            );
        }
    }

    #[BeforeStage('php', [self::class, 'makeForUnix'], 'php')]
    #[PatchDescription('Patch Makefile to fix //lib path for Linux builds')]
    public function tryPatchMakefileUnix(): void
    {
        if (SystemTarget::getTargetOS() !== 'Linux') {
            return;
        }

        // replace //lib with /lib in Makefile
        shell()->cd(SOURCE_PATH . '/php-src')->exec('sed -i "s|//lib|/lib|g" Makefile');
    }

    #[Stage]
    public function makeForUnix(TargetPackage $package, PackageInstaller $installer): void
    {
        V2CompatLayer::emitPatchPoint('before-php-make');

        logger()->info('cleaning up php-src build files');
        shell()->cd($package->getSourceDir())->exec('make clean');

        if ($installer->isPackageResolved('php-cli')) {
            $package->runStage([self::class, 'makeCliForUnix']);
        }
        if ($installer->isPackageResolved('php-cgi')) {
            $package->runStage([self::class, 'makeCgiForUnix']);
        }
        if ($installer->isPackageResolved('php-fpm')) {
            $package->runStage([self::class, 'makeFpmForUnix']);
        }
        if ($installer->isPackageResolved('php-micro')) {
            $package->runStage([self::class, 'makeMicroForUnix']);
        }
        if ($installer->isPackageResolved('php-embed')) {
            $package->runStage([self::class, 'makeEmbedForUnix']);
        }
    }

    #[Stage]
    public function makeCliForUnix(TargetPackage $package, PackageInstaller $installer, PackageBuilder $builder): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('make cli'));
        $concurrency = $builder->concurrency;
        $vars = $this->makeVars($installer);
        $makeArgs = $this->makeVarsToArgs($vars);
        shell()->cd($package->getSourceDir())
            ->setEnv($vars)
            ->exec("make -j{$concurrency} {$makeArgs} cli");

        $builder->deployBinary("{$package->getSourceDir()}/sapi/cli/php", BUILD_BIN_PATH . '/php');
        $package->setOutput('Binary path for cli SAPI', BUILD_BIN_PATH . '/php');
    }

    #[Stage]
    public function makeCgiForUnix(TargetPackage $package, PackageInstaller $installer, PackageBuilder $builder): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('make cgi'));
        $concurrency = $builder->concurrency;
        $vars = $this->makeVars($installer);
        $makeArgs = $this->makeVarsToArgs($vars);
        shell()->cd($package->getSourceDir())
            ->setEnv($vars)
            ->exec("make -j{$concurrency} {$makeArgs} cgi");

        $builder->deployBinary("{$package->getSourceDir()}/sapi/cgi/php-cgi", BUILD_BIN_PATH . '/php-cgi');
        $package->setOutput('Binary path for cgi SAPI', BUILD_BIN_PATH . '/php-cgi');
    }

    #[Stage]
    public function makeFpmForUnix(TargetPackage $package, PackageInstaller $installer, PackageBuilder $builder): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('make fpm'));
        $concurrency = $builder->concurrency;
        $vars = $this->makeVars($installer);
        $makeArgs = $this->makeVarsToArgs($vars);
        shell()->cd($package->getSourceDir())
            ->setEnv($vars)
            ->exec("make -j{$concurrency} {$makeArgs} fpm");

        $builder->deployBinary("{$package->getSourceDir()}/sapi/fpm/php-fpm", BUILD_BIN_PATH . '/php-fpm');
        $package->setOutput('Binary path for fpm SAPI', BUILD_BIN_PATH . '/php-fpm');
    }

    #[Stage]
    #[PatchDescription('Patch phar extension for micro SAPI to support compressed phar')]
    public function makeMicroForUnix(TargetPackage $package, PackageInstaller $installer, PackageBuilder $builder): void
    {
        $phar_patched = false;
        try {
            if ($installer->isPackageResolved('ext-phar')) {
                $phar_patched = true;
                SourcePatcher::patchMicroPhar(self::getPHPVersionID());
            }
            InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('make micro'));
            // apply --with-micro-fake-cli option
            $vars = $this->makeVars($installer);
            $vars['EXTRA_CFLAGS'] .= $package->getBuildOption('with-micro-fake-cli', false) ? ' -DPHP_MICRO_FAKE_CLI' : '';
            $makeArgs = $this->makeVarsToArgs($vars);
            // build
            shell()->cd($package->getSourceDir())
                ->setEnv($vars)
                ->exec("make -j{$builder->concurrency} {$makeArgs} micro");

            $dst = BUILD_BIN_PATH . '/micro.sfx';
            $builder->deployBinary($package->getSourceDir() . '/sapi/micro/micro.sfx', $dst);
            // patch after UPX-ed micro.sfx (Linux only)
            if (SystemTarget::getTargetOS() === 'Linux' && $builder->getOption('with-upx-pack')) {
                // cut binary with readelf to remove UPX extra segment
                [$ret, $out] = shell()->execWithResult("readelf -l {$dst} | awk '/LOAD|GNU_STACK/ {getline; print \\$1, \\$2, \\$3, \\$4, \\$6, \\$7}'");
                $out[1] = explode(' ', $out[1]);
                $offset = $out[1][0];
                if ($ret !== 0 || !str_starts_with($offset, '0x')) {
                    throw new PatchException('phpmicro UPX patcher', 'Cannot find offset in readelf output');
                }
                $offset = hexdec($offset);
                // remove upx extra wastes
                file_put_contents($dst, substr(file_get_contents($dst), 0, $offset));
            }
            $package->setOutput('Binary path for micro SAPI', $dst);
        } finally {
            if ($phar_patched) {
                SourcePatcher::unpatchMicroPhar();
            }
        }
    }

    #[Stage]
    public function makeEmbedForUnix(TargetPackage $package, PackageInstaller $installer, PackageBuilder $builder): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('make embed'));
        $shared_exts = array_filter(
            $installer->getResolvedPackages(),
            static fn ($x) => $x instanceof PhpExtensionPackage && $x->isBuildShared() && $x->isBuildWithPhp()
        );
        $install_modules = $shared_exts ? 'install-modules' : '';

        // detect changes in module path
        $diff = new DirDiff(BUILD_MODULES_PATH, true);

        $root = BUILD_ROOT_PATH;
        $sed_prefix = SystemTarget::getTargetOS() === 'Darwin' ? 'sed -i ""' : 'sed -i';

        shell()->cd($package->getSourceDir())
            ->setEnv($this->makeVars($installer))
            ->exec("{$sed_prefix} \"s|^EXTENSION_DIR = .*|EXTENSION_DIR = /" . basename(BUILD_MODULES_PATH) . '|" Makefile')
            ->exec("make -j{$builder->concurrency} INSTALL_ROOT={$root} install-sapi {$install_modules} install-build install-headers install-programs");

        // ------------- SPC_CMD_VAR_PHP_EMBED_TYPE=shared -------------

        // process libphp.so for shared embed
        $suffix = SystemTarget::getTargetOS() === 'Darwin' ? 'dylib' : 'so';
        $libphp_so = "{$package->getLibDir()}/libphp.{$suffix}";
        if (file_exists($libphp_so)) {
            // rename libphp.so if -release is set
            if (SystemTarget::getTargetOS() === 'Linux') {
                $this->processLibphpSoFile($libphp_so, $installer);
            }
            // deploy
            $builder->deployBinary($libphp_so, $libphp_so, false);
            $package->setOutput('Library path for embed SAPI', $libphp_so);
        }

        // process shared extensions that built-with-php
        $increment_files = $diff->getChangedFiles();
        $files = [];
        foreach ($increment_files as $increment_file) {
            $builder->deployBinary($increment_file, $increment_file, false);
            $files[] = basename($increment_file);
        }
        if (!empty($files)) {
            $package->setOutput('Built shared extensions', implode(', ', $files));
        }

        // ------------- SPC_CMD_VAR_PHP_EMBED_TYPE=static -------------

        // process libphp.a for static embed
        if (!file_exists("{$package->getLibDir()}/libphp.a")) {
            return;
        }
        $ar = getenv('AR') ?: 'ar';
        $libphp_a = "{$package->getLibDir()}/libphp.a";
        shell()->exec("{$ar} -t {$libphp_a} | grep '\\.a$' | xargs -n1 {$ar} d {$libphp_a}");
        UnixUtil::exportDynamicSymbols($libphp_a);

        // deploy embed php scripts
        $package->runStage([$this, 'patchUnixEmbedScripts']);
    }

    #[Stage]
    public function unixBuildSharedExt(PackageInstaller $installer, ToolchainInterface $toolchain): void
    {
        // collect shared extensions
        /** @var PhpExtensionPackage[] $shared_extensions */
        $shared_extensions = array_filter(
            $installer->getResolvedPackages(PhpExtensionPackage::class),
            fn ($x) => $x->isBuildShared() && !$x->isBuildWithPhp()
        );
        if (!empty($shared_extensions)) {
            if ($toolchain->isStatic()) {
                throw new WrongUsageException(
                    "You're building against musl libc statically (the default on Linux), but you're trying to build shared extensions.\n" .
                    'Static musl libc does not implement `dlopen`, so your php binary is not able to load shared extensions.' . "\n" .
                    'Either use SPC_LIBC=glibc to link against glibc on a glibc OS, or use SPC_TARGET="native-native-musl -dynamic" to link against musl libc dynamically using `zig cc`.'
                );
            }
            FileSystem::createDir(BUILD_MODULES_PATH);

            // backup
            FileSystem::backupFile(BUILD_BIN_PATH . '/php-config');
            FileSystem::backupFile(BUILD_LIB_PATH . '/php/build/phpize.m4');

            FileSystem::replaceFileLineContainsString(BUILD_BIN_PATH . '/php-config', 'extension_dir=', 'extension_dir="' . BUILD_MODULES_PATH . '"');
            FileSystem::replaceFileStr(BUILD_LIB_PATH . '/php/build/phpize.m4', 'test "[$]$1" = "no" && $1=yes', '# test "[$]$1" = "no" && $1=yes');
        }

        try {
            logger()->debug('Building shared extensions...');
            foreach ($shared_extensions as $extension) {
                InteractiveTerm::setMessage('Building shared PHP extension: ' . ConsoleColor::yellow($extension->getName()));
                $extension->buildShared();
            }
        } finally {
            // restore php-config
            if (!empty($shared_extensions)) {
                FileSystem::restoreBackupFile(BUILD_BIN_PATH . '/php-config');
                FileSystem::restoreBackupFile(BUILD_LIB_PATH . '/php/build/phpize.m4');
            }
        }
    }

    #[Stage]
    public function smokeTestForUnix(PackageBuilder $builder, TargetPackage $package, PackageInstaller $installer): void
    {
        // analyse --no-smoke-test option
        $no_smoke_test = $builder->getOption('no-smoke-test');
        // validate option
        $option = match ($no_smoke_test) {
            false => false, // default value, run all smoke tests
            null => 'all', // --no-smoke-test without value, skip all smoke tests
            default => parse_comma_list($no_smoke_test), // --no-smoke-test=cli,fpm, skip specified smoke tests
        };
        $valid_tests = ['cli', 'cgi', 'micro', 'micro-exts', 'embed', 'frankenphp'];
        // compat: --without-micro-ext-test is equivalent to --no-smoke-test=micro-exts
        if ($builder->getOption('without-micro-ext-test', false)) {
            $valid_tests = array_diff($valid_tests, ['micro-exts']);
        }
        if (is_array($option)) {
            /*
            1. if option is not in valid tests, throw WrongUsageException
            2. if all passed options are valid, remove them from $valid_tests, and run the remaining tests
            */
            foreach ($option as $test) {
                if (!in_array($test, $valid_tests, true)) {
                    throw new WrongUsageException("Invalid value for --no-smoke-test: {$test}. Valid values are: " . implode(', ', $valid_tests));
                }
                $valid_tests = array_diff($valid_tests, [$test]);
            }
        } elseif ($option === 'all') {
            $valid_tests = [];
        }
        // run cli tests
        if (in_array('cli', $valid_tests, true) && $installer->isPackageResolved('php-cli')) {
            $package->runStage([$this, 'smokeTestCliForUnix']);
        }
        // run cgi tests
        if (in_array('cgi', $valid_tests, true) && $installer->isPackageResolved('php-cgi')) {
            $package->runStage([$this, 'smokeTestCgiForUnix']);
        }
        // run micro tests
        if (in_array('micro', $valid_tests, true) && $installer->isPackageResolved('php-micro')) {
            $skipExtTest = !in_array('micro-exts', $valid_tests, true);
            $package->runStage([$this, 'smokeTestMicroForUnix'], ['skipExtTest' => $skipExtTest]);
        }
        // run embed tests
        if (in_array('embed', $valid_tests, true) && $installer->isPackageResolved('php-embed')) {
            $package->runStage([$this, 'smokeTestEmbedForUnix']);
        }
    }

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function build(TargetPackage $package): void
    {
        // frankenphp is not a php sapi, it's a standalone Go binary that depends on libphp.a (embed)
        if ($package->getName() === 'frankenphp') {
            /* @var php $this */
            $package->runStage([$this, 'buildFrankenphpForUnix']);
            $package->runStage([$this, 'smokeTestFrankenphpForUnix']);
            return;
        }
        // virtual target, do nothing
        if ($package->getName() !== 'php') {
            return;
        }

        $package->runStage([$this, 'buildconfForUnix']);
        $package->runStage([$this, 'configureForUnix']);
        $package->runStage([$this, 'makeForUnix']);

        $package->runStage([$this, 'unixBuildSharedExt']);
        $package->runStage([$this, 'smokeTestForUnix']);
    }

    /**
     * Patch phpize and php-config if needed
     */
    #[Stage]
    public function patchUnixEmbedScripts(): void
    {
        // patch phpize
        if (file_exists(BUILD_BIN_PATH . '/phpize')) {
            logger()->debug('Patching phpize prefix');
            FileSystem::replaceFileStr(BUILD_BIN_PATH . '/phpize', "prefix=''", "prefix='" . BUILD_ROOT_PATH . "'");
            FileSystem::replaceFileStr(BUILD_BIN_PATH . '/phpize', 's##', 's#/usr/local#');
            $this->setOutput('phpize script path for embed SAPI', BUILD_BIN_PATH . '/phpize');
        }
        // patch php-config
        if (file_exists(BUILD_BIN_PATH . '/php-config')) {
            logger()->debug('Patching php-config prefix and libs order');
            $php_config_str = FileSystem::readFile(BUILD_BIN_PATH . '/php-config');
            $php_config_str = str_replace('prefix=""', 'prefix="' . BUILD_ROOT_PATH . '"', $php_config_str);
            // move mimalloc to the beginning of libs
            $php_config_str = preg_replace('/(libs=")(.*?)\s*(' . preg_quote(BUILD_LIB_PATH, '/') . '\/mimalloc\.o)\s*(.*?)"/', '$1$3 $2 $4"', $php_config_str);
            // move lstdc++ to the end of libs
            $php_config_str = preg_replace('/(libs=")(.*?)\s*(-lstdc\+\+)\s*(.*?)"/', '$1$2 $4 $3"', $php_config_str);
            FileSystem::writeFile(BUILD_BIN_PATH . '/php-config', $php_config_str);
            $this->setOutput('php-config script path for embed SAPI', BUILD_BIN_PATH . '/php-config');
        }
    }

    #[Stage]
    public function smokeTestCliForUnix(PackageInstaller $installer): void
    {
        InteractiveTerm::setMessage('Running basic php-cli smoke test');
        [$ret, $output] = shell()->execWithResult(BUILD_BIN_PATH . '/php -n -r "echo \"hello\";"');
        $raw_output = implode('', $output);
        if ($ret !== 0 || trim($raw_output) !== 'hello') {
            throw new ValidationException("cli failed smoke test. code: {$ret}, output: {$raw_output}", validation_module: 'php-cli smoke test');
        }

        $exts = $installer->getResolvedPackages(PhpExtensionPackage::class);
        foreach ($exts as $ext) {
            InteractiveTerm::setMessage('Running php-cli smoke test for ' . ConsoleColor::yellow($ext->getExtensionName()) . ' extension');
            $ext->runSmokeTestCliUnix();
        }
    }

    #[Stage]
    public function smokeTestCgiForUnix(): void
    {
        InteractiveTerm::setMessage('Running basic php-cgi smoke test');
        [$ret, $output] = shell()->execWithResult("echo '<?php echo \"<h1>Hello, World!</h1>\";' | " . BUILD_BIN_PATH . '/php-cgi -n');
        $raw_output = implode('', $output);
        if ($ret !== 0 || !str_contains($raw_output, 'Hello, World!') || !str_contains($raw_output, 'text/html')) {
            throw new ValidationException("cgi failed smoke test. code: {$ret}, output: {$raw_output}", validation_module: 'php-cgi smoke test');
        }
    }

    #[Stage]
    public function smokeTestMicroForUnix(PackageInstaller $installer, bool $skipExtTest = false): void
    {
        $micro_sfx = BUILD_BIN_PATH . '/micro.sfx';

        // micro_ext_test
        InteractiveTerm::setMessage('Running php-micro ext smoke test');
        $content = $skipExtTest
            ? '<?php echo "[micro-test-start][micro-test-end]";'
            : $this->generateMicroExtTests($installer);
        $test_file = SOURCE_PATH . '/micro_ext_test.exe';
        if (file_exists($test_file)) {
            @unlink($test_file);
        }
        file_put_contents($test_file, file_get_contents($micro_sfx) . $content);
        chmod($test_file, 0755);
        [$ret, $out] = shell()->execWithResult($test_file);
        $raw_out = trim(implode('', $out));
        if ($ret !== 0 || !str_starts_with($raw_out, '[micro-test-start]') || !str_ends_with($raw_out, '[micro-test-end]')) {
            throw new ValidationException(
                "micro_ext_test failed. code: {$ret}, output: {$raw_out}",
                validation_module: 'phpmicro sanity check item [micro_ext_test]'
            );
        }

        // micro_zend_bug_test
        InteractiveTerm::setMessage('Running php-micro zend bug smoke test');
        $content = file_get_contents(ROOT_DIR . '/src/globals/common-tests/micro_zend_mm_heap_corrupted.txt');
        $test_file = SOURCE_PATH . '/micro_zend_bug_test.exe';
        if (file_exists($test_file)) {
            @unlink($test_file);
        }
        file_put_contents($test_file, file_get_contents($micro_sfx) . $content);
        chmod($test_file, 0755);
        [$ret, $out] = shell()->execWithResult($test_file);
        if ($ret !== 0) {
            $raw_out = trim(implode('', $out));
            throw new ValidationException(
                "micro_zend_bug_test failed. code: {$ret}, output: {$raw_out}",
                validation_module: 'phpmicro sanity check item [micro_zend_bug_test]'
            );
        }
    }

    #[Stage]
    public function smokeTestEmbedForUnix(PackageInstaller $installer, ToolchainInterface $toolchain): void
    {
        $sample_file_path = SOURCE_PATH . '/embed-test';
        FileSystem::createDir($sample_file_path);
        // copy embed test files
        copy(ROOT_DIR . '/src/globals/common-tests/embed.c', $sample_file_path . '/embed.c');
        copy(ROOT_DIR . '/src/globals/common-tests/embed.php', $sample_file_path . '/embed.php');

        $config = new SPCConfigUtil()->config(array_map(fn ($x) => $x->getName(), $installer->getResolvedPackages()));
        $lens = "{$config['cflags']} {$config['ldflags']} {$config['libs']}";
        if ($toolchain->isStatic()) {
            $lens .= ' -static';
        }

        $dynamic_exports = '';
        $envVars = [];
        $embedType = 'static';
        if (getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') === 'shared') {
            $embedType = 'shared';
            $libPathKey = SystemTarget::getTargetOS() === 'Darwin' ? 'DYLD_LIBRARY_PATH' : 'LD_LIBRARY_PATH';
            $envVars[$libPathKey] = BUILD_LIB_PATH . (($existing = getenv($libPathKey)) ? ':' . $existing : '');
            FileSystem::removeFileIfExists(BUILD_LIB_PATH . '/libphp.a');
        } else {
            $suffix = SystemTarget::getTargetOS() === 'Darwin' ? 'dylib' : 'so';
            foreach (glob(BUILD_LIB_PATH . "/libphp*.{$suffix}") as $file) {
                unlink($file);
            }
            // calling getDynamicExportedSymbols on non-Linux is okay
            if ($dynamic_exports = UnixUtil::getDynamicExportedSymbols(BUILD_LIB_PATH . '/libphp.a')) {
                $dynamic_exports = ' ' . $dynamic_exports;
            }
        }

        $cc = getenv('CC');
        InteractiveTerm::setMessage('Running php-embed build smoke test');
        [$ret, $out] = shell()->cd($sample_file_path)->execWithResult("{$cc} -o embed embed.c {$lens}{$dynamic_exports}");
        if ($ret !== 0) {
            throw new ValidationException(
                'embed failed to build. Error message: ' . implode("\n", $out),
                validation_module: $embedType . ' libphp embed build smoke test'
            );
        }

        InteractiveTerm::setMessage('Running php-embed run smoke test');
        [$ret, $output] = shell()->cd($sample_file_path)->setEnv($envVars)->execWithResult('./embed');
        if ($ret !== 0 || trim(implode('', $output)) !== 'hello') {
            throw new ValidationException(
                'embed failed to run. Error message: ' . implode("\n", $output),
                validation_module: $embedType . ' libphp embed run smoke test'
            );
        }
    }

    /**
     * Seek php-src/config.log when building PHP, add it to exception.
     */
    protected function seekPhpSrcLogFileOnException(callable $callback, string $source_dir): void
    {
        try {
            $callback();
        } catch (SPCException $e) {
            if (file_exists("{$source_dir}/config.log")) {
                $e->addExtraLogFile('php-src config.log', 'php-src.config.log');
                copy("{$source_dir}/config.log", SPC_LOGS_DIR . '/php-src.config.log');
            }
            throw $e;
        }
    }

    /**
     * Generate micro extension test php code.
     */
    private function generateMicroExtTests(PackageInstaller $installer): string
    {
        $php = "<?php\n\necho '[micro-test-start]' . PHP_EOL;\n";
        foreach ($installer->getResolvedPackages(PhpExtensionPackage::class) as $ext) {
            if (!$ext->isBuildStatic()) {
                continue;
            }
            $ext_name = $ext->getDistName();
            if (!empty($ext_name)) {
                $php .= "echo 'Running micro with {$ext_name} test' . PHP_EOL;\n";
                $php .= "assert(extension_loaded('{$ext_name}'));\n\n";
            }
        }
        $php .= "echo '[micro-test-end]';\n";
        return $php;
    }

    /**
     * Rename libphp.so to libphp-<release>.so if -release is set in LDFLAGS.
     */
    private function processLibphpSoFile(string $libphpSo, PackageInstaller $installer): void
    {
        $ldflags = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS') ?: '';
        $libDir = BUILD_LIB_PATH;
        $modulesDir = BUILD_MODULES_PATH;
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
                foreach ($installer->getResolvedPackages(PhpExtensionPackage::class) as $ext) {
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
            $output = implode("\n", $output);
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
    }

    /**
     * Make environment variables for php make.
     * This will call SPCConfigUtil to generate proper LDFLAGS and LIBS for static linking.
     */
    private function makeVars(PackageInstaller $installer): array
    {
        $config = new SPCConfigUtil(['libs_only_deps' => true])->config(array_map(fn ($x) => $x->getName(), $installer->getResolvedPackages()));
        $static = ApplicationContext::get(ToolchainInterface::class)->isStatic() ? '-all-static' : '';
        $pie = SystemTarget::getTargetOS() === 'Linux' ? '-pie' : '';

        // Append SPC_EXTRA_LIBS to libs for dynamic linking support (e.g., X11)
        $extra_libs = getenv('SPC_EXTRA_LIBS') ?: '';
        $libs = trim($config['libs'] . ' ' . $extra_libs);

        return array_filter([
            'EXTRA_CFLAGS' => getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS'),
            'EXTRA_LDFLAGS_PROGRAM' => getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS') . "{$config['ldflags']} {$static} {$pie}",
            'EXTRA_LDFLAGS' => $config['ldflags'],
            'EXTRA_LIBS' => $libs,
        ]);
    }

    /**
     * Convert make variables array to command line argument string.
     * This is needed because make command line arguments have higher priority than environment variables.
     */
    private function makeVarsToArgs(array $vars): string
    {
        $args = [];
        foreach ($vars as $key => $value) {
            if (trim($value) !== '') {
                $args[] = $key . '=' . escapeshellarg($value);
            }
        }
        return implode(' ', $args);
    }
}
