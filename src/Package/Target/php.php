<?php

declare(strict_types=1);

namespace Package\Target;

use Package\Target\php\frankenphp;
use Package\Target\php\unix;
use Package\Target\php\windows;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Info;
use StaticPHP\Attribute\Package\InitPackage;
use StaticPHP\Attribute\Package\ResolveBuild;
use StaticPHP\Attribute\Package\Target;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
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
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\SourcePatcher;
use StaticPHP\Util\V2CompatLayer;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[Target('php')]
#[Target('php-cli')]
#[Target('php-fpm')]
#[Target('php-micro')]
#[Target('php-cgi')]
#[Target('php-embed')]
#[Target('frankenphp')]
class php extends TargetPackage
{
    use unix;
    use windows;
    use frankenphp;

    /** @var string[] Supported major PHP versions */
    public const array SUPPORTED_MAJOR_VERSIONS = ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];

    /**
     * Get PHP version ID from php_version.h
     *
     * @param  null|string $from_custom_source    Where to read php_version.h from custom source
     * @param  bool        $return_null_if_failed Whether to return null if failed to get version ID
     * @return null|int    PHP version ID (e.g., 80400 for PHP 8.4.0) or null if failed
     */
    public static function getPHPVersionID(?string $from_custom_source = null, bool $return_null_if_failed = false): ?int
    {
        $source_dir = $from_custom_source ?? ArtifactLoader::getArtifactInstance('php-src')->getSourceDir();
        if (!file_exists("{$source_dir}/main/php_version.h")) {
            if ($return_null_if_failed) {
                return null;
            }
            throw new WrongUsageException('PHP source files are not available, you need to download them first');
        }

        $file = file_get_contents("{$source_dir}/main/php_version.h");
        if (preg_match('/PHP_VERSION_ID (\d+)/', $file, $match) !== 0) {
            return intval($match[1]);
        }

        if ($return_null_if_failed) {
            return null;
        }
        throw new WrongUsageException('PHP version file format is malformed, please remove "./source/php-src" dir and download/extract again');
    }

    /**
     * Get PHP version from php_version.h
     *
     * @param  null|string $from_custom_source    Where to read php_version.h from custom source
     * @param  bool        $return_null_if_failed Whether to return null if failed to get version
     * @return null|string PHP version (e.g., "8.4.0") or null if failed
     */
    public static function getPHPVersion(?string $from_custom_source = null, bool $return_null_if_failed = false): ?string
    {
        $source_dir = $from_custom_source ?? ArtifactLoader::getArtifactInstance('php-src')->getSourceDir();
        if (!file_exists("{$source_dir}/main/php_version.h")) {
            if ($return_null_if_failed) {
                return null;
            }
            throw new WrongUsageException('PHP source files are not available, you need to download them first');
        }

        $file = file_get_contents("{$source_dir}/main/php_version.h");
        if (preg_match('/PHP_VERSION "(.*)"/', $file, $match) !== 0) {
            return $match[1];
        }

        if ($return_null_if_failed) {
            return null;
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
        $package->addBuildOption('no-smoke-test', null, InputOption::VALUE_OPTIONAL, 'Disable smoke test for specific SAPIs, or all if no value provided', false);

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

        // frankenphp depends on embed SAPI (libphp.a)
        if ($package->getName() === 'frankenphp') {
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
            // frankenphp doesn't support windows, BSD is currently not supported by StaticPHP
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
            'Strip Binaries' => $package->getBuildOption('no-strip') ? 'No' : 'Yes',
            'Enable ZTS' => $package->getBuildOption('enable-zts') ? 'Yes' : 'No',
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
            ApplicationContext::invoke([SourcePatcher::class, 'patchHardcodedINI'], [$package->getSourceDir(), $custom_ini]);
        }

        // Patch StaticPHP version
        // detect patch (remove this when 8.3 deprecated)
        $file = FileSystem::readFile("{$package->getSourceDir()}/main/main.c");
        if (!str_contains($file, 'StaticPHP.version')) {
            $version = SPC_VERSION;
            logger()->debug('Inserting StaticPHP.version to php-src');
            $file = str_replace('PHP_INI_BEGIN()', "PHP_INI_BEGIN()\n\tPHP_INI_ENTRY(\"StaticPHP.version\",\t\"{$version}\",\tPHP_INI_ALL,\tNULL)", $file);
            FileSystem::writeFile("{$package->getSourceDir()}/main/main.c", $file);
        }

        // clean old modules that may conflict with the new php build
        FileSystem::removeDir(BUILD_MODULES_PATH);
    }

    private function makeStaticExtensionString(PackageInstaller $installer): string
    {
        $arg = [];
        foreach ($installer->getResolvedPackages() as $package) {
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
}
