<?php

declare(strict_types=1);

namespace Package\Target\php;

use Package\Target\php;
use StaticPHP\Attribute\Package\Stage;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Toolchain\GccNativeToolchain;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use StaticPHP\Util\SPCConfigUtil;
use StaticPHP\Util\System\LinuxUtil;
use ZM\Logger\ConsoleColor;

trait frankenphp
{
    #[Stage]
    public function buildFrankenphpForUnix(TargetPackage $package, PackageInstaller $installer, ToolchainInterface $toolchain, PackageBuilder $builder): void
    {
        if (getenv('GOROOT') === false) {
            throw new SPCInternalException('go-xcaddy is not initialized properly. GOROOT is not set.');
        }

        // process --with-frankenphp-app option
        InteractiveTerm::setMessage('Building frankenphp: ' . ConsoleColor::yellow('processing --with-frankenphp-app option'));
        $package->runStage([$this, 'processFrankenphpApp']);

        // modules
        $no_brotli = $installer->isPackageResolved('brotli') ? '' : ',nobrotli';
        $no_watcher = $installer->isPackageResolved('watcher') ? '' : ',nowatcher';
        $xcaddy_modules = getenv('SPC_CMD_VAR_FRANKENPHP_XCADDY_MODULES'); // from env.ini
        $source_dir = $package->getSourceDir();

        $xcaddy_modules = preg_replace('#--with github.com/dunglas/frankenphp\S*#', '', $xcaddy_modules);
        $xcaddy_modules = "--with github.com/dunglas/frankenphp={$source_dir} " .
            "--with github.com/dunglas/frankenphp/caddy={$source_dir}/caddy {$xcaddy_modules}";

        // disable caddy-cbrotli if brotli is not built
        if (!$installer->isPackageResolved('brotli') && str_contains($xcaddy_modules, '--with github.com/dunglas/caddy-cbrotli')) {
            logger()->warning('caddy-cbrotli module is enabled, but brotli library is not built. Disabling caddy-cbrotli.');
            $xcaddy_modules = str_replace('--with github.com/dunglas/caddy-cbrotli', '', $xcaddy_modules);
        }

        $frankenphp_version = $this->getFrankenPHPVersion($package);
        $libphp_version = php::getPHPVersion();
        $dynamic_exports = '';
        if (getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') === 'shared') {
            $libphp_version = preg_replace('/\.\d+$/', '', $libphp_version);
        } elseif ($dynamicSymbolsArgument = LinuxUtil::getDynamicExportedSymbols(BUILD_LIB_PATH . '/libphp.a')) {
            $dynamic_exports = ' ' . $dynamicSymbolsArgument;
        }

        // full-static build flags
        if ($toolchain->isStatic()) {
            $extLdFlags = "-extldflags '-static-pie -Wl,-z,stack-size=0x80000{$dynamic_exports} {$package->getLibExtraLdFlags()}'";
            $muslTags = 'static_build,';
            $staticFlags = '-static-pie';
        } else {
            $extLdFlags = "-extldflags '-pie{$dynamic_exports} {$package->getLibExtraLdFlags()}'";
            $muslTags = '';
            $staticFlags = '';
        }

        $resolved = array_keys($installer->getResolvedPackages());
        // remove self from deps
        $resolved = array_filter($resolved, fn ($pkg_name) => $pkg_name !== $package->getName());
        $config = new SPCConfigUtil()->config($resolved);
        $cflags = "{$package->getLibExtraCFlags()} {$config['cflags']} " . getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') . " -DFRANKENPHP_VERSION={$frankenphp_version}";
        $libs = $config['libs'];

        // Go's gcc driver doesn't automatically link against -lgcov or -lrt. Ugly, but necessary fix.
        if ((str_contains((string) getenv('SPC_DEFAULT_C_FLAGS'), '-fprofile') ||
                str_contains((string) getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS'), '-fprofile')) &&
            $toolchain instanceof GccNativeToolchain) {
            $cflags .= ' -Wno-error=missing-profile';
            $libs .= ' -lgcov';
        }

        $env = [
            'CGO_ENABLED' => '1',
            'CGO_CFLAGS' => clean_spaces($cflags),
            'CGO_LDFLAGS' => "{$package->getLibExtraLdFlags()} {$staticFlags} {$config['ldflags']} {$libs}",
            'XCADDY_GO_BUILD_FLAGS' => '-buildmode=pie ' .
                '-ldflags \"-linkmode=external ' . $extLdFlags . ' ' .
                '-X \'github.com/caddyserver/caddy/v2/modules/caddyhttp.ServerHeader=FrankenPHP Caddy\' ' .
                '-X \'github.com/caddyserver/caddy/v2.CustomVersion=FrankenPHP ' .
                "v{$frankenphp_version} PHP {$libphp_version} Caddy'\\\" " .
                "-tags={$muslTags}nobadger,nomysql,nopgx{$no_brotli}{$no_watcher}",
            'LD_LIBRARY_PATH' => BUILD_LIB_PATH,
        ];
        InteractiveTerm::setMessage('Building frankenphp: ' . ConsoleColor::yellow('building with xcaddy'));
        shell()->cd(BUILD_LIB_PATH)
            ->setEnv($env)
            ->exec("xcaddy build --output frankenphp {$xcaddy_modules}");

        $builder->deployBinary(BUILD_LIB_PATH . '/frankenphp', BUILD_BIN_PATH . '/frankenphp');
        $package->setOutput('Binary path for FrankenPHP SAPI', BUILD_BIN_PATH . '/frankenphp');
    }

    #[Stage]
    public function smokeTestFrankenphpForUnix(): void
    {
        InteractiveTerm::setMessage('Running FrankenPHP smoke test');
        $frankenphp = BUILD_BIN_PATH . '/frankenphp';
        if (!file_exists($frankenphp)) {
            throw new ValidationException(
                "FrankenPHP binary not found: {$frankenphp}",
                validation_module: 'FrankenPHP smoke test'
            );
        }
        $prefix = PHP_OS_FAMILY === 'Darwin' ? 'DYLD_' : 'LD_';
        [$ret, $output] = shell()
            ->setEnv(["{$prefix}LIBRARY_PATH" => BUILD_LIB_PATH])
            ->execWithResult("{$frankenphp} version");
        if ($ret !== 0 || !str_contains(implode('', $output), 'FrankenPHP')) {
            throw new ValidationException(
                'FrankenPHP failed smoke test: ret[' . $ret . ']. out[' . implode('', $output) . ']',
                validation_module: 'FrankenPHP smoke test'
            );
        }
    }

    /**
     * Process the --with-frankenphp-app option
     * Creates app.tar and app.checksum in source/frankenphp directory
     */
    #[Stage]
    public function processFrankenphpApp(TargetPackage $package): void
    {
        $frankenphpSourceDir = $package->getSourceDir();

        $frankenphpAppPath = $package->getBuildOption('with-frankenphp-app');

        if ($frankenphpAppPath) {
            if (!is_dir($frankenphpAppPath)) {
                throw new WrongUsageException("The path provided to --with-frankenphp-app is not a valid directory: {$frankenphpAppPath}");
            }
            $appTarPath = "{$frankenphpSourceDir}/app.tar";
            logger()->info("Creating app.tar from {$frankenphpAppPath}");

            shell()->exec('tar -cf ' . escapeshellarg($appTarPath) . ' -C ' . escapeshellarg($frankenphpAppPath) . ' .');

            $checksum = hash_file('md5', $appTarPath);
            file_put_contents($frankenphpSourceDir . '/app_checksum.txt', $checksum);
        } else {
            FileSystem::removeFileIfExists("{$frankenphpSourceDir}/app.tar");
            FileSystem::removeFileIfExists("{$frankenphpSourceDir}/app_checksum.txt");
            file_put_contents("{$frankenphpSourceDir}/app.tar", '');
            file_put_contents("{$frankenphpSourceDir}/app_checksum.txt", '');
        }
    }

    protected function getFrankenPHPVersion(TargetPackage $package): string
    {
        if ($version = getenv('FRANKENPHP_VERSION')) {
            return $version;
        }
        $frankenphpSourceDir = $package->getSourceDir();
        $goModPath = $frankenphpSourceDir . '/caddy/go.mod';

        if (!file_exists($goModPath)) {
            throw new SPCInternalException("FrankenPHP caddy/go.mod file not found at {$goModPath}, why did we not download FrankenPHP?");
        }

        $content = file_get_contents($goModPath);
        if (preg_match('/github\.com\/dunglas\/frankenphp\s+v?(\d+\.\d+\.\d+)/', $content, $matches)) {
            return $matches[1];
        }

        throw new SPCInternalException('Could not find FrankenPHP version in caddy/go.mod');
    }
}
