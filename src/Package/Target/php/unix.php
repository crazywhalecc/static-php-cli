<?php

declare(strict_types=1);

namespace Package\Target\php;

use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Stage;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\SPCException;
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
        $args[] = $installer->isPackageResolved('php-fpm') ? '--enable-fpm' : '--disable-fpm';
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

            $builder->deployBinary($package->getSourceDir() . '/sapi/micro/micro.sfx', BUILD_BIN_PATH . '/micro.sfx');
            $package->setOutput('Binary path for micro SAPI', BUILD_BIN_PATH . '/micro.sfx');
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

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function build(TargetPackage $package): void
    {
        // virtual target, do nothing
        if ($package->getName() !== 'php') {
            return;
        }

        $package->runStage([$this, 'buildconfForUnix']);
        $package->runStage([$this, 'configureForUnix']);
        $package->runStage([$this, 'makeForUnix']);

        $package->runStage([$this, 'unixBuildSharedExt']);
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
