<?php

declare(strict_types=1);

namespace Package\Target;

use StaticPHP\Artifact\ArtifactLoader;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Info;
use StaticPHP\Attribute\Package\InitPackage;
use StaticPHP\Attribute\Package\ResolveBuild;
use StaticPHP\Attribute\Package\Stage;
use StaticPHP\Attribute\Package\Target;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\SPCException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\Package;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PackageLoader;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Toolchain\ToolchainManager;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use StaticPHP\Util\SourcePatcher;
use StaticPHP\Util\SPCConfigUtil;
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
class php
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
    public function resolveBuild(TargetPackage $package): array
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
            if (!PackageLoader::hasPackage($extension)) {
                throw new WrongUsageException("Extension [{$extname}] does not exist. Please check your extension name.");
            }
            $instance = PackageLoader::getPackage($extension);
            if (!$instance instanceof PhpExtensionPackage) {
                throw new WrongUsageException("Package [{$extension}] is not a PHP extension package");
            }
            // set build static/shared
            if (in_array($extname, $static_extensions)) {
                $instance->setBuildStatic();
            }
            if (in_array($extname, $shared_extensions)) {
                $instance->setBuildShared();
            }
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
            $installer->getBuildPackage('php-cli') ? 'cli' : null,
            $installer->getBuildPackage('php-fpm') ? 'fpm' : null,
            $installer->getBuildPackage('php-micro') ? 'micro' : null,
            $installer->getBuildPackage('php-cgi') ? 'cgi' : null,
            $installer->getBuildPackage('php-embed') ? 'embed' : null,
            $installer->getBuildPackage('frankenphp') ? 'frankenphp' : null,
        ]);
        $static_extensions = array_filter($installer->getResolvedPackages(), fn ($x) => $x->getType() === 'php-extension');
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

    #[BeforeStage('php', 'unix-buildconf')]
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

    #[Stage('unix-buildconf')]
    public function buildconfForUnix(TargetPackage $package): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('./buildconf'));
        V2CompatLayer::emitPatchPoint('before-php-buildconf');
        shell()->cd($package->getSourceDir())->exec(getenv('SPC_CMD_PREFIX_PHP_BUILDCONF'));
    }

    #[Stage('unix-configure')]
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
        $args[] = $installer->isBuildPackage('php-cli') ? '--enable-cli' : '--disable-cli';
        $args[] = $installer->isBuildPackage('php-fpm') ? '--enable-fpm' : '--disable-fpm';
        $args[] = $installer->isBuildPackage('php-micro') ? match (SystemTarget::getTargetOS()) {
            'Linux' => '--enable-micro=all-static',
            default => '--enable-micro',
        } : null;
        $args[] = $installer->isBuildPackage('php-cgi') ? '--enable-cgi' : '--disable-cgi';
        $embed_type = getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') ?: 'static';
        $args[] = $installer->isBuildPackage('php-embed') ? "--enable-embed={$embed_type}" : '--disable-embed';
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

    #[Stage('unix-make')]
    public function makeForUnix(TargetPackage $package, PackageInstaller $installer): void
    {
        V2CompatLayer::emitPatchPoint('before-php-make');

        logger()->info('cleaning up php-src build files');
        shell()->cd($package->getSourceDir())->exec('make clean');

        if ($installer->isBuildPackage('php-cli')) {
            $package->runStage('unix-make-cli');
        }
        if ($installer->isBuildPackage('php-fpm')) {
            $package->runStage('unix-make-fpm');
        }
        if ($installer->isBuildPackage('php-cgi')) {
            $package->runStage('unix-make-cgi');
        }
    }

    #[Stage('unix-make-cli')]
    public function makeCliForUnix(TargetPackage $package, PackageInstaller $installer, PackageBuilder $builder): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('make cli'));
        $concurrency = $builder->concurrency;
        shell()->cd($package->getSourceDir())
            ->setEnv($this->makeVars($installer))
            ->exec("make -j{$concurrency} cli");
    }

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function build(TargetPackage $package): void
    {
        // virtual target, do nothing
        if ($package->getName() !== 'php') {
            return;
        }

        $package->runStage('unix-buildconf');
        $package->runStage('unix-configure');
        $package->runStage('unix-make');
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
}
