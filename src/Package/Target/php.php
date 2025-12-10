<?php

declare(strict_types=1);

namespace Package\Target;

use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Info;
use StaticPHP\Attribute\Package\InitPackage;
use StaticPHP\Attribute\Package\ResolveBuild;
use StaticPHP\Attribute\Package\Stage;
use StaticPHP\Attribute\Package\Target;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\SPCException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\Package;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Registry\ArtifactLoader;
use StaticPHP\Registry\PackageLoader;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Toolchain\ToolchainManager;
use StaticPHP\Util\DirDiff;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use StaticPHP\Util\SourcePatcher;
use StaticPHP\Util\SPCConfigUtil;
use StaticPHP\Util\System\UnixUtil;
use StaticPHP\Util\V2CompatLayer;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use ZM\Logger\ConsoleColor;

#[Target('php')]
#[Target('php-cli')]
#[Target('php-fpm')]
#[Target('php-micro')]
#[Target('php-cgi')]
#[Target('php-embed')]
#[Target('frankenphp')]
class php extends TargetPackage
{
    public static function getPHPVersionID(): int
    {
        $artifact = ArtifactLoader::getArtifactInstance('php-src');
        if (!file_exists("{$artifact->getSourceDir()}/main/php_version.h")) {
            throw new WrongUsageException('PHP source files are not available, you need to download them first');
        }

        $file = file_get_contents("{$artifact->getSourceDir()}/main/php_version.h");
        if (preg_match('/PHP_VERSION_ID (\d+)/', $file, $match) !== 0) {
            return intval($match[1]);
        }

        throw new WrongUsageException('PHP version file format is malformed, please remove "./source/php-src" dir and download/extract again');
    }

    #[InitPackage]
    public function init(TargetPackage $package): void
    {
        // universal build options (may move to base class later)
        $package->addBuildOption('with-added-patch', 'P', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Inject patch script outside');

        // basic build argument and options for PHP
        $package->addBuildArgument('extensions', InputArgument::REQUIRED, 'Comma-separated list of static extensions to build');
        $package->addBuildOption('no-strip', null, null, 'build without strip, keep symbols to debug');
        $package->addBuildOption('with-upx-pack', null, null, 'Compress / pack binary using UPX tool (linux/windows only)');

        // php configure and extra patch options
        $package->addBuildOption('disable-opcache-jit', null, null, 'Disable opcache jit');
        $package->addBuildOption('with-config-file-path', null, InputOption::VALUE_REQUIRED, 'Set the path in which to look for php.ini', PHP_OS_FAMILY === 'Windows' ? null : '/usr/local/etc/php');
        $package->addBuildOption('with-config-file-scan-dir', null, InputOption::VALUE_REQUIRED, 'Set the directory to scan for .ini files after reading php.ini', PHP_OS_FAMILY === 'Windows' ? null : '/usr/local/etc/php/conf.d');
        $package->addBuildOption('with-hardcoded-ini', 'I', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Patch PHP source code, inject hardcoded INI');
        $package->addBuildOption('enable-zts', null, null, 'Enable thread safe support');

        // phpmicro build options
        if ($package->getName() === 'php' || $package->getName() === 'php-micro') {
            $package->addBuildOption('with-micro-fake-cli', null, null, 'Let phpmicro\'s PHP_SAPI use "cli" instead of "micro"');
            $package->addBuildOption('without-micro-ext-test', null, null, 'Disable phpmicro with extension test code');
            $package->addBuildOption('with-micro-logo', null, InputOption::VALUE_REQUIRED, 'Use custom .ico for micro.sfx (windows only)');
            $package->addBuildOption('enable-micro-win32', null, null, 'Enable win32 mode for phpmicro (Windows only)');
        }

        // frankenphp build options
        if ($package->getName() === 'php' || $package->getName() === 'frankenphp') {
            $package->addBuildOption('with-frankenphp-app', null, InputOption::VALUE_REQUIRED, 'Path to a folder to be embedded in FrankenPHP');
        }

        // embed build options
        if ($package->getName() === 'php' || $package->getName() === 'php-embed') {
            $package->addBuildOption('build-shared', 'D', InputOption::VALUE_REQUIRED, 'Shared extensions to build, comma separated', '');
        }

        // legacy php target build options
        V2CompatLayer::addLegacyBuildOptionsForPhp($package);
        if ($package->getName() === 'php') {
            $package->addBuildOption('build-micro', null, null, 'Build micro SAPI');
            $package->addBuildOption('build-cli', null, null, 'Build cli SAPI');
            $package->addBuildOption('build-fpm', null, null, 'Build fpm SAPI (not available on Windows)');
            $package->addBuildOption('build-embed', null, null, 'Build embed SAPI (not available on Windows)');
            $package->addBuildOption('build-frankenphp', null, null, 'Build FrankenPHP SAPI (not available on Windows)');
            $package->addBuildOption('build-cgi', null, null, 'Build cgi SAPI');
            $package->addBuildOption('build-all', null, null, 'Build all SAPI');
        }
    }

    #[ResolveBuild]
    public function resolveBuild(TargetPackage $package, PackageInstaller $installer): array
    {
        // Parse extensions and additional packages for all php-* targets
        $static_extensions = parse_extension_list($package->getBuildArgument('extensions'));
        $additional_libraries = parse_comma_list($package->getBuildOption('with-libs'));
        $additional_packages = parse_comma_list($package->getBuildOption('with-packages'));
        $additional_packages = array_merge($additional_libraries, $additional_packages);
        $shared_extensions = parse_extension_list($package->getBuildOption('build-shared') ?? []);

        $extensions_pkg = array_map(
            fn ($x) => "ext-{$x}",
            array_values(array_unique([...$static_extensions, ...$shared_extensions]))
        );

        // get instances
        foreach ($extensions_pkg as $extension) {
            $extname = substr($extension, 4);
            $config = PackageConfig::get($extension, 'php-extension', []);
            if (!PackageLoader::hasPackage($extension)) {
                throw new WrongUsageException("Extension [{$extname}] does not exist. Please check your extension name.");
            }
            $instance = PackageLoader::getPackage($extension);
            if (!$instance instanceof PhpExtensionPackage) {
                throw new WrongUsageException("Package [{$extension}] is not a PHP extension package");
            }
            // set build static/shared
            if (in_array($extname, $static_extensions)) {
                if (($config['build-static'] ?? true) === false) {
                    throw new WrongUsageException("Extension [{$extname}] cannot be built as static extension.");
                }
                $instance->setBuildStatic();
            }
            if (in_array($extname, $shared_extensions)) {
                if (($config['build-shared'] ?? true) === false) {
                    throw new WrongUsageException("Extension [{$extname}] cannot be built as shared extension, please remove it from --build-shared option.");
                }
                $instance->setBuildShared();
                $instance->setBuildWithPhp($config['build-with-php'] ?? false);
            }
        }

        // building shared extensions need embed SAPI
        if (!empty($shared_extensions) && !$package->getBuildOption('build-embed', false) && $package->getName() === 'php') {
            $installer->addBuildPackage('php-embed');
        }

        return [...$extensions_pkg, ...$additional_packages];
    }

    #[Validate]
    public function validate(Package $package): void
    {
        // frankenphp
        if ($package->getName() === 'frankenphp' && $package instanceof TargetPackage) {
            if (!$package->getBuildOption('enable-zts')) {
                throw new WrongUsageException('FrankenPHP SAPI requires ZTS enabled PHP, build with `--enable-zts`!');
            }
            // frankenphp doesn't support windows, BSD is currently not supported by static-php-cli
            if (!in_array(PHP_OS_FAMILY, ['Linux', 'Darwin'])) {
                throw new WrongUsageException('FrankenPHP SAPI is only available on Linux and macOS!');
            }
        }
        // linux does not support loading shared libraries when target is pure static
        $embed_type = getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') ?: 'static';
        if (SystemTarget::getTargetOS() === 'Linux' && ApplicationContext::get(ToolchainInterface::class)->isStatic() && $embed_type === 'shared') {
            throw new WrongUsageException(
                'Linux does not support loading shared libraries when linking libc statically. ' .
                'Change SPC_CMD_VAR_PHP_EMBED_TYPE to static.'
            );
        }
    }

    #[Info]
    public function info(Package $package, PackageInstaller $installer): array
    {
        /** @var TargetPackage $package */
        if ($package->getName() !== 'php') {
            return [];
        }
        $sapis = array_filter([
            $installer->isPackageResolved('php-cli') ? 'cli' : null,
            $installer->isPackageResolved('php-fpm') ? 'fpm' : null,
            $installer->isPackageResolved('php-micro') ? 'micro' : null,
            $installer->isPackageResolved('php-cgi') ? 'cgi' : null,
            $installer->isPackageResolved('php-embed') ? 'embed' : null,
            $installer->isPackageResolved('frankenphp') ? 'frankenphp' : null,
        ]);
        $static_extensions = array_filter($installer->getResolvedPackages(), fn ($x) => $x instanceof PhpExtensionPackage && $x->isBuildStatic());
        $shared_extensions = parse_extension_list($package->getBuildOption('build-shared') ?? []);
        $install_packages = array_filter($installer->getResolvedPackages(), fn ($x) => $x->getType() !== 'php-extension' && $x->getName() !== 'php' && !str_starts_with($x->getName(), 'php-'));
        return [
            'Build OS' => SystemTarget::getTargetOS() . ' (' . SystemTarget::getTargetArch() . ')',
            'Build Target' => getenv('SPC_TARGET') ?: '',
            'Build Toolchain' => ToolchainManager::getToolchainClass(),
            'Build SAPI' => implode(', ', $sapis),
            'Static Extensions (' . count($static_extensions) . ')' => implode(',', array_map(fn ($x) => substr($x->getName(), 4), $static_extensions)),
            'Shared Extensions (' . count($shared_extensions) . ')' => implode(',', $shared_extensions),
            'Install Packages (' . count($install_packages) . ')' => implode(',', array_map(fn ($x) => $x->getName(), $install_packages)),
        ];
    }

    #[BeforeStage('php', 'build')]
    public function beforeBuild(PackageBuilder $builder, Package $package): void
    {
        // Process -I option
        $custom_ini = [];
        foreach ($builder->getOption('with-hardcoded-ini', []) as $value) {
            [$source_name, $ini_value] = explode('=', $value, 2);
            $custom_ini[$source_name] = $ini_value;
            logger()->info("Adding hardcoded INI [{$source_name} = {$ini_value}]");
        }
        if (!empty($custom_ini)) {
            SourcePatcher::patchHardcodedINI($package->getSourceDir(), $custom_ini);
        }

        // Patch StaticPHP version
        // detect patch (remove this when 8.3 deprecated)
        $file = FileSystem::readFile("{$package->getSourceDir()}/main/main.c");
        if (!str_contains($file, 'static-php-cli.version')) {
            $version = SPC_VERSION;
            logger()->debug('Inserting static-php-cli.version to php-src');
            $file = str_replace('PHP_INI_BEGIN()', "PHP_INI_BEGIN()\n\tPHP_INI_ENTRY(\"static-php-cli.version\",\t\"{$version}\",\tPHP_INI_ALL,\tNULL)", $file);
            FileSystem::writeFile("{$package->getSourceDir()}/main/main.c", $file);
        }

        // clean old modules that may conflict with the new php build
        FileSystem::removeDir(BUILD_MODULES_PATH);
    }

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
        shell()->cd($package->getSourceDir())
            ->setEnv($this->makeVars($installer))
            ->exec("make -j{$concurrency} cli");

        $builder->deployBinary("{$package->getSourceDir()}/sapi/cli/php", BUILD_BIN_PATH . '/php');
        $package->setOutput('Binary path for cli SAPI', BUILD_BIN_PATH . '/php');
    }

    #[Stage]
    public function makeCgiForUnix(TargetPackage $package, PackageInstaller $installer, PackageBuilder $builder): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('make cgi'));
        $concurrency = $builder->concurrency;
        shell()->cd($package->getSourceDir())
            ->setEnv($this->makeVars($installer))
            ->exec("make -j{$concurrency} cgi");

        $builder->deployBinary("{$package->getSourceDir()}/sapi/cgi/php-cgi", BUILD_BIN_PATH . '/php-cgi');
        $package->setOutput('Binary path for cgi SAPI', BUILD_BIN_PATH . '/php-cgi');
    }

    #[Stage]
    public function makeFpmForUnix(TargetPackage $package, PackageInstaller $installer, PackageBuilder $builder): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('make fpm'));
        $concurrency = $builder->concurrency;
        shell()->cd($package->getSourceDir())
            ->setEnv($this->makeVars($installer))
            ->exec("make -j{$concurrency} fpm");

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
            // build
            shell()->cd($package->getSourceDir())
                ->setEnv($vars)
                ->exec("make -j{$builder->concurrency} micro");

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
        $package->runStage([$this, 'patchEmbedScripts']);
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
    public function patchEmbedScripts(): void
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

    private function makeStaticExtensionString(PackageInstaller $installer): string
    {
        $arg = [];
        foreach ($installer->getResolvedPackages() as $package) {
            /** @var PhpExtensionPackage $package */
            if ($package->getType() !== 'php-extension' || !$package instanceof PhpExtensionPackage) {
                continue;
            }

            // build-shared=true, build-static=false, build-with-php=true
            if ($package->isBuildShared() && !$package->isBuildStatic() && $package->isBuildWithPhp()) {
                $arg[] = $package->getPhpConfigureArg(SystemTarget::getTargetOS(), true);
            } elseif ($package->isBuildStatic()) {
                $arg[] = $package->getPhpConfigureArg(SystemTarget::getTargetOS(), false);
            }
        }
        $str = implode(' ', $arg);
        logger()->debug("Static extension configure args: {$str}");
        return $str;
    }

    /**
     * Make environment variables for php make.
     * This will call SPCConfigUtil to generate proper LDFLAGS and LIBS for static linking.
     */
    private function makeVars(PackageInstaller $installer): array
    {
        $config = (new SPCConfigUtil(['libs_only_deps' => true]))->config(array_map(fn ($x) => $x->getName(), $installer->getResolvedPackages()));
        $static = ApplicationContext::get(ToolchainInterface::class)->isStatic() ? '-all-static' : '';
        $pie = SystemTarget::getTargetOS() === 'Linux' ? '-pie' : '';

        return array_filter([
            'EXTRA_CFLAGS' => getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS'),
            'EXTRA_LDFLAGS_PROGRAM' => getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS') . "{$config['ldflags']} {$static} {$pie}",
            'EXTRA_LDFLAGS' => $config['ldflags'],
            'EXTRA_LIBS' => $config['libs'],
        ]);
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
}
