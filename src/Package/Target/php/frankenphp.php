<?php

declare(strict_types=1);

namespace Package\Target\php;

use Package\Target\php;
use StaticPHP\Attribute\Package\Stage;
use StaticPHP\Config\PackageConfig;
use StaticPHP\Exception\EnvironmentException;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Toolchain\GccNativeToolchain;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use StaticPHP\Util\SPCConfigUtil;
use StaticPHP\Util\System\LinuxUtil;
use StaticPHP\Util\System\WindowsUtil;
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
                '-X \'github.com/caddyserver/caddy/v2.CustomBinaryName=frankenphp\' ' .
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
    public function smokeTestFrankenphpForUnix(PackageBuilder $builder): void
    {
        // analyse --no-smoke-test option
        $no_smoke_test = $builder->getOption('no-smoke-test', false);
        $option = match ($no_smoke_test) {
            false => false, // default value, run all smoke tests
            null => 'all', // --no-smoke-test without value, skip all smoke tests
            default => parse_comma_list($no_smoke_test), // --no-smoke-test=frankenphp,...
        };
        if ($option === 'all' || (is_array($option) && in_array('frankenphp', $option, true))) {
            return;
        }

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
            $frankenphpAppPath = trim($frankenphpAppPath, "\"'");
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

    #[Stage]
    public function buildFrankenphpForWindows(TargetPackage $package, PackageInstaller $installer, PackageBuilder $builder): void
    {
        if (getenv('GOROOT') === false) {
            throw new EnvironmentException('go-win is not initialized properly. GOROOT is not set.');
        }

        $clang_info = WindowsUtil::findClang();
        if ($clang_info === false) {
            throw new EnvironmentException(
                'Clang not found. FrankenPHP Windows build requires the LLVM toolchain component of Visual Studio. ' .
                'Install it in Visual Studio Installer under "C++ Clang tools for Windows", or set the CC environment variable.'
            );
        }

        $frankenphp_version = $this->getFrankenPHPVersion($package);
        $libphp_version = php::getPHPVersion();
        $major = intdiv(PHP_VERSION_ID, 10000);
        $source_dir = $package->getSourceDir();

        // collect PHP include paths in clang -I format (not MSVC /I).
        // Use forward slashes and NO quotes around paths: when Go passes CGO_CFLAGS tokens
        // directly to clang via exec(), any embedded quotes become literal characters in
        // the argument string and break include-path resolution.
        $include = str_replace('\\', '/', BUILD_INCLUDE_PATH);
        // The PHP source root is needed so that Windows-only headers installed only in
        // the source tree (e.g. win32/ioutil.h, win32/winutil.h) can be found via their
        // relative #include paths like `#include "win32/ioutil.h"`.
        $php_src = str_replace('\\', '/', SOURCE_PATH . '/php-src');
        $cgo_cflags = implode(' ', [
            "-I{$include}",
            "-I{$include}/php",
            "-I{$include}/php/main",
            "-I{$include}/php/Zend",
            "-I{$include}/php/TSRM",
            "-I{$include}/php/ext",
            "-I{$php_src}",
            "-I{$php_src}/main",
            "-I{$php_src}/ext",
            "-I{$php_src}/Zend",
            "-I{$php_src}/TSRM",
            "-DFRANKENPHP_VERSION={$frankenphp_version}",
            '-DZEND_ENABLE_STATIC_TSRMLS_CACHE=1',
        ]);

        $dep_libs = [];
        foreach ($installer->getResolvedPackages(LibraryPackage::class) as $lib) {
            foreach (PackageConfig::get($lib->getName(), 'static-libs', []) as $lib_file) {
                if (file_exists("{$package->getLibDir()}\\{$lib_file}")) {
                    $lib_name = preg_replace('/\.lib$/i', '', $lib_file);
                    $dep_libs[] = "-l{$lib_name}";
                }
            }
        }

        $dep_libs = array_unique($dep_libs);
        $lib_dir = str_replace('\\', '/', BUILD_LIB_PATH);
        $php_embed_lib = "-lphp{$major}embed";
        $win_sys_libs = '-lkernel32 -lole32 -luser32 -ladvapi32 -lshell32 -lws2_32 -ldnsapi -lpsapi -lbcrypt';
        $cgo_ldflags = clean_spaces(implode(' ', array_filter([
            "-L{$lib_dir}",
            $php_embed_lib,
            implode(' ', $dep_libs),
            $win_sys_libs,
            '-llibcmt',
            '-Wl,/NODEFAULTLIB:msvcrt',
            '-Wl,/NODEFAULTLIB:msvcrtd',
            '-Wl,/FORCE:MULTIPLE',
        ])));

        // build tags: skip watcher (no inotify/kqueue on Windows)
        $go_build_tags = 'nobadger,nomysql,nopgx,nowatcher';
        if (!$installer->isPackageResolved('brotli')) {
            $go_build_tags .= ',nobrotli';
        }

        $go_ldflags =
            '-extldflags=-fuse-ld=lld ' .
            "-X 'github.com/caddyserver/caddy/v2/modules/caddyhttp.ServerHeader=FrankenPHP Caddy' " .
            "-X 'github.com/caddyserver/caddy/v2.CustomBinaryName=frankenphp' " .
            "-X 'github.com/caddyserver/caddy/v2.CustomVersion=FrankenPHP v{$frankenphp_version} PHP {$libphp_version} Caddy'";

        // CGO on Windows tokenizes CC/CXX like a shell command line, splitting on spaces.
        // Paths like "C:\Program Files\..." break because only "C:\Program" is used.
        // Fix: prepend clang's directory to PATH and use plain executable names instead,
        // which matches FrankenPHP's official CI approach (CC=clang, CXX=clang++).
        $clang_dir = dirname($clang_info['clang']);
        $env = [
            'CGO_ENABLED' => '1',
            'CC' => 'clang.exe',
            'CXX' => 'clang++.exe',
            'PATH' => $clang_dir . ';' . getenv('PATH'),
            'CGO_CFLAGS' => clean_spaces($cgo_cflags),
            'CGO_LDFLAGS' => $cgo_ldflags,
        ];

        InteractiveTerm::setMessage('Building frankenphp: ' . ConsoleColor::yellow('embedding Windows metadata'));
        $package->runStage([$this, 'embedFrankenphpWindowsMetadata']);

        InteractiveTerm::setMessage('Building frankenphp: ' . ConsoleColor::yellow('building with go build'));

        cmd()->cd("{$source_dir}\\caddy\\frankenphp")
            ->setEnv($env)
            ->exec("go build -v -tags \"{$go_build_tags}\" -ldflags \"{$go_ldflags}\" -o frankenphp.exe .");

        $builder->deployBinary("{$source_dir}\\caddy\\frankenphp\\frankenphp.exe", BUILD_BIN_PATH . '\frankenphp.exe');
        $package->setOutput('Binary path for FrankenPHP SAPI', BUILD_BIN_PATH . '\frankenphp.exe');
    }

    /**
     * Embed Windows PE metadata (version info + icon) into resource.syso so that
     * go build picks it up automatically. Mirrors the official FrankenPHP Windows CI.
     */
    #[Stage]
    public function embedFrankenphpWindowsMetadata(TargetPackage $package): void
    {
        $frankenphp_version = $this->getFrankenPHPVersion($package);
        $source_dir = $package->getSourceDir();
        $build_dir = "{$source_dir}\\caddy\\frankenphp";

        [$p1, $p2, $p3] = explode('.', $frankenphp_version);
        $major = (int) $p1;
        $minor = (int) $p2;
        $patch = (int) $p3;

        $version_info = [
            'FixedFileInfo' => [
                'FileVersion' => ['Major' => $major, 'Minor' => $minor, 'Patch' => $patch, 'Build' => 0],
                'ProductVersion' => ['Major' => $major, 'Minor' => $minor, 'Patch' => $patch, 'Build' => 0],
            ],
            'StringFileInfo' => [
                'CompanyName' => 'FrankenPHP',
                'FileDescription' => 'The modern PHP app server',
                'FileVersion' => $frankenphp_version,
                'InternalName' => 'frankenphp',
                'OriginalFilename' => 'frankenphp.exe',
                'LegalCopyright' => '(c) 2022 Kévin Dunglas, MIT License',
                'ProductName' => 'FrankenPHP',
                'ProductVersion' => $frankenphp_version,
                'Comments' => 'https://frankenphp.dev/',
            ],
            'VarFileInfo' => [
                'Translation' => ['LangID' => 9, 'CharsetID' => 1200],
            ],
        ];

        file_put_contents("{$build_dir}\\versioninfo.json", json_encode($version_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Install goversioninfo if not already installed.
        // GOPATH is set by the go-win artifact initializer via GlobalEnvManager::putenv().
        $goversioninfo = getenv('GOROOT') . '\bin\goversioninfo.exe';
        if (!file_exists($goversioninfo)) {
            cmd()->exec('go install github.com/josephspurrier/goversioninfo/cmd/goversioninfo@latest');
        }

        // -64: embed as 64-bit resource; -icon: relative path from the build dir to the repo root icon.
        cmd()->cd($build_dir)
            ->exec("\"{$goversioninfo}\" -64 -icon  {$package->getSourceDir()}\\frankenphp.ico versioninfo.json -o resource.syso");
    }

    #[Stage]
    public function smokeTestFrankenphpForWindows(PackageBuilder $builder): void
    {
        // analyse --no-smoke-test option
        $no_smoke_test = $builder->getOption('no-smoke-test', false);
        $option = match ($no_smoke_test) {
            false => false, // default value, run all smoke tests
            null => 'all', // --no-smoke-test without value, skip all smoke tests
            default => parse_comma_list($no_smoke_test), // --no-smoke-test=frankenphp,...
        };
        if ($option === 'all' || (is_array($option) && in_array('frankenphp', $option, true))) {
            return;
        }

        InteractiveTerm::setMessage('Running FrankenPHP smoke test');
        $frankenphp = BUILD_BIN_PATH . '\frankenphp.exe';
        if (!file_exists($frankenphp)) {
            throw new ValidationException(
                "FrankenPHP binary not found: {$frankenphp}",
                validation_module: 'FrankenPHP smoke test'
            );
        }
        [$ret, $output] = cmd()->execWithResult("{$frankenphp} version");
        if ($ret !== 0 || !str_contains(implode('', $output), 'FrankenPHP')) {
            throw new ValidationException(
                'FrankenPHP failed smoke test: ret[' . $ret . ']. out[' . implode('', $output) . ']',
                validation_module: 'FrankenPHP smoke test'
            );
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
