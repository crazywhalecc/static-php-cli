<?php

declare(strict_types=1);

namespace SPC\builder\unix;

use SPC\builder\BuilderBase;
use SPC\builder\linux\SystemUtil;
use SPC\builder\linux\SystemUtil as LinuxSystemUtil;
use SPC\exception\SPCException;
use SPC\exception\SPCInternalException;
use SPC\exception\ValidationException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\FileSystem;
use SPC\store\pkg\GoXcaddy;
use SPC\store\SourceManager;
use SPC\toolchain\GccNativeToolchain;
use SPC\toolchain\ToolchainManager;
use SPC\util\DependencyUtil;
use SPC\util\GlobalEnvManager;
use SPC\util\SPCConfigUtil;
use SPC\util\SPCTarget;

abstract class UnixBuilderBase extends BuilderBase
{
    /** @var string cflags */
    public string $arch_c_flags;

    /** @var string C++ flags */
    public string $arch_cxx_flags;

    /** @var string LD flags */
    public string $arch_ld_flags;

    public function proveLibs(array $sorted_libraries): void
    {
        // search all supported libs
        $support_lib_list = [];
        $classes = FileSystem::getClassesPsr4(
            ROOT_DIR . '/src/SPC/builder/' . osfamily2dir() . '/library',
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
            if (!in_array(Config::getLib($library, 'type', 'lib'), ['lib', 'package'])) {
                continue;
            }
            // if some libs are not supported (but in config "lib.json", throw exception)
            if (!isset($support_lib_list[$library])) {
                $os = match (PHP_OS_FAMILY) {
                    'Linux' => 'Linux',
                    'Darwin' => 'macOS',
                    'Windows' => 'Windows',
                    'BSD' => 'FreeBSD',
                    default => PHP_OS_FAMILY,
                };
                throw new WrongUsageException("library [{$library}] is in the lib.json list but not supported to build on {$os}.");
            }
            $lib = new ($support_lib_list[$library])($this);
            $this->addLib($lib);
        }

        // calculate and check dependencies
        foreach ($this->libs as $lib) {
            $lib->calcDependency();
        }
        $this->lib_list = $sorted_libraries;
    }

    /**
     * Strip unneeded symbols from binary file.
     */
    public function stripBinary(string $binary_path): void
    {
        shell()->exec(match (PHP_OS_FAMILY) {
            'Darwin' => "strip -S {$binary_path}",
            'Linux' => "strip --strip-unneeded {$binary_path}",
            default => throw new SPCInternalException('stripBinary is only supported on Linux and macOS'),
        });
    }

    /**
     * Extract debug information from binary file.
     *
     * @param string $binary_path the path to the binary file, including executables, shared libraries, etc
     */
    public function extractDebugInfo(string $binary_path): string
    {
        $target_dir = BUILD_ROOT_PATH . '/debug';
        FileSystem::createDir($target_dir);
        $basename = basename($binary_path);
        $debug_file = "{$target_dir}/{$basename}" . (PHP_OS_FAMILY === 'Darwin' ? '.dwarf' : '.debug');
        if (PHP_OS_FAMILY === 'Darwin') {
            shell()->exec("dsymutil -f {$binary_path} -o {$debug_file}");
        } elseif (PHP_OS_FAMILY === 'Linux') {
            if ($eu_strip = SystemUtil::findCommand('eu-strip')) {
                shell()
                    ->exec("{$eu_strip} -f {$debug_file} {$binary_path}")
                    ->exec("objcopy --add-gnu-debuglink={$debug_file} {$binary_path}");
            } else {
                shell()
                    ->exec("objcopy --only-keep-debug {$binary_path} {$debug_file}")
                    ->exec("objcopy --add-gnu-debuglink={$debug_file} {$binary_path}");
            }
        } else {
            throw new SPCInternalException('extractDebugInfo is only supported on Linux and macOS');
        }
        return $debug_file;
    }

    /**
     * Deploy the binary file from src to dst.
     */
    public function deployBinary(string $src, string $dst, bool $executable = true): string
    {
        logger()->debug('Deploying binary from ' . $src . ' to ' . $dst);

        // file must exists
        if (!file_exists($src)) {
            throw new SPCInternalException("Deploy failed. Cannot find file: {$src}");
        }
        // dst dir must exists
        FileSystem::createDir(dirname($dst));

        // ignore copy to self
        if (realpath($src) !== realpath($dst)) {
            shell()->exec('cp ' . escapeshellarg($src) . ' ' . escapeshellarg($dst));
        }

        // file exist
        if (!file_exists($dst)) {
            throw new SPCInternalException("Deploy failed. Cannot find file after copy: {$dst}");
        }

        if (!$this->getOption('no-strip')) {
            // extract debug info
            $this->extractDebugInfo($dst);
            // extra strip
            $this->stripBinary($dst);
        }

        // UPX for linux
        $upx_option = $this->getOption('with-upx-pack');
        if ($upx_option && PHP_OS_FAMILY === 'Linux' && $executable) {
            if ($this->getOption('no-strip')) {
                logger()->warning('UPX compression is not recommended when --no-strip is enabled.');
            }
            logger()->info("Compressing {$dst} with UPX");
            shell()->exec(getenv('UPX_EXEC') . " --best {$dst}");
        }

        return $dst;
    }

    /**
     * Sanity check after build complete.
     */
    protected function sanityCheck(int $build_target): void
    {
        // sanity check for php-cli
        if (($build_target & BUILD_TARGET_CLI) === BUILD_TARGET_CLI) {
            logger()->info('running cli sanity check');
            [$ret, $output] = shell()->execWithResult(BUILD_BIN_PATH . '/php -n -r "echo \"hello\";"');
            $raw_output = implode('', $output);
            if ($ret !== 0 || trim($raw_output) !== 'hello') {
                throw new ValidationException("cli failed sanity check. code: {$ret}, output: {$raw_output}", validation_module: 'php-cli sanity check');
            }

            foreach ($this->getExts() as $ext) {
                logger()->debug('testing ext: ' . $ext->getName());
                $ext->runCliCheckUnix();
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
                file_put_contents($test_file, file_get_contents(SOURCE_PATH . '/php-src/sapi/micro/micro.sfx') . $task['content']);
                chmod($test_file, 0755);
                [$ret, $out] = shell()->execWithResult($test_file);
                foreach ($task['conditions'] as $condition => $closure) {
                    if (!$closure($ret, $out)) {
                        $raw_out = trim(implode('', $out));
                        throw new ValidationException(
                            "failure info: {$condition}, code: {$ret}, output: {$raw_out}",
                            validation_module: "phpmicro sanity check item [{$task_name}]"
                        );
                    }
                }
            }
        }

        // sanity check for php-cgi
        if (($build_target & BUILD_TARGET_CGI) === BUILD_TARGET_CGI) {
            logger()->info('running cgi sanity check');
            [$ret, $output] = shell()->execWithResult("echo '<?php echo \"<h1>Hello, World!</h1>\";' | " . BUILD_BIN_PATH . '/php-cgi -n');
            $raw_output = implode('', $output);
            if ($ret !== 0 || !str_contains($raw_output, 'Hello, World!') || !str_contains($raw_output, 'text/html')) {
                throw new ValidationException("cgi failed sanity check. code: {$ret}, output: {$raw_output}", validation_module: 'php-cgi sanity check');
            }
        }

        // sanity check for embed
        if (($build_target & BUILD_TARGET_EMBED) === BUILD_TARGET_EMBED) {
            logger()->info('running embed sanity check');
            $sample_file_path = SOURCE_PATH . '/embed-test';
            if (!is_dir($sample_file_path)) {
                @mkdir($sample_file_path);
            }
            // copy embed test files
            copy(ROOT_DIR . '/src/globals/common-tests/embed.c', $sample_file_path . '/embed.c');
            copy(ROOT_DIR . '/src/globals/common-tests/embed.php', $sample_file_path . '/embed.php');
            $util = new SPCConfigUtil($this);
            $config = $util->config($this->ext_list, $this->lib_list, $this->getOption('with-suggested-exts'), $this->getOption('with-suggested-libs'));
            $lens = "{$config['cflags']} {$config['ldflags']} {$config['libs']}";
            if (SPCTarget::isStatic()) {
                $lens .= ' -static';
            }
            $dynamic_exports = '';
            $embedType = 'static';
            // if someone changed to EMBED_TYPE=shared, we need to add LD_LIBRARY_PATH
            if (getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') === 'shared') {
                $embedType = 'shared';
                if (PHP_OS_FAMILY === 'Darwin') {
                    $ext_path = 'DYLD_LIBRARY_PATH=' . BUILD_LIB_PATH . ':$DYLD_LIBRARY_PATH ';
                } else {
                    $ext_path = 'LD_LIBRARY_PATH=' . BUILD_LIB_PATH . ':$LD_LIBRARY_PATH ';
                }
                FileSystem::removeFileIfExists(BUILD_LIB_PATH . '/libphp.a');
            } else {
                $ext_path = '';
                $suffix = PHP_OS_FAMILY === 'Darwin' ? 'dylib' : 'so';
                foreach (glob(BUILD_LIB_PATH . "/libphp*.{$suffix}") as $file) {
                    unlink($file);
                }
                // calling linux system util in other unix OS is okay
                if ($dynamic_exports = LinuxSystemUtil::getDynamicExportedSymbols(BUILD_LIB_PATH . '/libphp.a')) {
                    $dynamic_exports = ' ' . $dynamic_exports;
                }
            }
            $cc = getenv('CC');

            [$ret, $out] = shell()->cd($sample_file_path)->execWithResult("{$cc} -o embed embed.c {$lens} {$dynamic_exports}");
            if ($ret !== 0) {
                throw new ValidationException(
                    'embed failed to build. Error message: ' . implode("\n", $out),
                    validation_module: $embedType . ' libphp embed build sanity check'
                );
            }
            [$ret, $output] = shell()->cd($sample_file_path)->execWithResult($ext_path . './embed');
            if ($ret !== 0 || trim(implode('', $output)) !== 'hello') {
                throw new ValidationException(
                    'embed failed to run. Error message: ' . implode("\n", $output),
                    validation_module: $embedType . ' libphp embed run sanity check'
                );
            }
        }

        // sanity check for frankenphp
        if (($build_target & BUILD_TARGET_FRANKENPHP) === BUILD_TARGET_FRANKENPHP) {
            logger()->info('running frankenphp sanity check');
            $frankenphp = BUILD_BIN_PATH . '/frankenphp';
            if (!file_exists($frankenphp)) {
                throw new ValidationException(
                    "FrankenPHP binary not found: {$frankenphp}",
                    validation_module: 'FrankenPHP sanity check'
                );
            }
            $prefix = PHP_OS_FAMILY === 'Darwin' ? 'DYLD_' : 'LD_';
            [$ret, $output] = shell()
                ->setEnv(["{$prefix}LIBRARY_PATH" => BUILD_LIB_PATH])
                ->execWithResult("{$frankenphp} version");
            if ($ret !== 0 || !str_contains(implode('', $output), 'FrankenPHP')) {
                throw new ValidationException(
                    'FrankenPHP failed sanity check: ret[' . $ret . ']. out[' . implode('', $output) . ']',
                    validation_module: 'FrankenPHP sanity check'
                );
            }
        }
    }

    /**
     * Deploy binaries that produces executable SAPI
     */
    protected function deploySAPIBinary(int $type): string
    {
        $src = match ($type) {
            BUILD_TARGET_CLI => SOURCE_PATH . '/php-src/sapi/cli/php',
            BUILD_TARGET_MICRO => SOURCE_PATH . '/php-src/sapi/micro/micro.sfx',
            BUILD_TARGET_FPM => SOURCE_PATH . '/php-src/sapi/fpm/php-fpm',
            BUILD_TARGET_CGI => SOURCE_PATH . '/php-src/sapi/cgi/php-cgi',
            BUILD_TARGET_FRANKENPHP => BUILD_BIN_PATH . '/frankenphp',
            default => throw new SPCInternalException("Deployment does not accept type {$type}"),
        };
        $dst = BUILD_BIN_PATH . '/' . basename($src);
        return $this->deployBinary($src, $dst);
    }

    /**
     * Run php clean
     */
    protected function cleanMake(): void
    {
        logger()->info('cleaning up php-src build files');
        shell()->cd(SOURCE_PATH . '/php-src')->exec('make clean');
    }

    /**
     * Patch phpize and php-config if needed
     */
    protected function patchPhpScripts(): void
    {
        // patch phpize
        if (file_exists(BUILD_BIN_PATH . '/phpize')) {
            logger()->debug('Patching phpize prefix');
            FileSystem::replaceFileStr(BUILD_BIN_PATH . '/phpize', "prefix=''", "prefix='" . BUILD_ROOT_PATH . "'");
            FileSystem::replaceFileStr(BUILD_BIN_PATH . '/phpize', 's##', 's#/usr/local#');
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
        }
        foreach ($this->getLibs() as $lib) {
            if ($lib->patchPhpConfig()) {
                logger()->debug("Library {$lib->getName()} patched php-config");
            }
        }
    }

    /**
     * Process the --with-frankenphp-app option
     * Creates app.tar and app.checksum in source/frankenphp directory
     */
    protected function processFrankenphpApp(): void
    {
        $frankenphpSourceDir = getenv('FRANKENPHP_SOURCE_PATH') ?: SOURCE_PATH . '/frankenphp';
        if (!is_dir($frankenphpSourceDir)) {
            SourceManager::initSource(['frankenphp'], ['frankenphp']);
        }
        $frankenphpAppPath = $this->getOption('with-frankenphp-app');

        if ($frankenphpAppPath) {
            if (!is_dir($frankenphpAppPath)) {
                throw new WrongUsageException("The path provided to --with-frankenphp-app is not a valid directory: {$frankenphpAppPath}");
            }
            $appTarPath = $frankenphpSourceDir . '/app.tar';
            logger()->info("Creating app.tar from {$frankenphpAppPath}");

            shell()->exec('tar -cf ' . escapeshellarg($appTarPath) . ' -C ' . escapeshellarg($frankenphpAppPath) . ' .');

            $checksum = hash_file('md5', $appTarPath);
            file_put_contents($frankenphpSourceDir . '/app_checksum.txt', $checksum);
        } else {
            FileSystem::removeFileIfExists($frankenphpSourceDir . '/app.tar');
            FileSystem::removeFileIfExists($frankenphpSourceDir . '/app_checksum.txt');
            file_put_contents($frankenphpSourceDir . '/app.tar', '');
            file_put_contents($frankenphpSourceDir . '/app_checksum.txt', '');
        }
    }

    protected function getFrankenPHPVersion(): string
    {
        if ($version = getenv('FRANKENPHP_VERSION')) {
            return $version;
        }
        $frankenphpSourceDir = getenv('FRANKENPHP_SOURCE_PATH') ?: SOURCE_PATH . '/frankenphp';
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

    protected function buildFrankenphp(): void
    {
        GlobalEnvManager::addPathIfNotExists(GoXcaddy::getPath());
        $this->processFrankenphpApp();
        $nobrotli = $this->getLib('brotli') === null ? ',nobrotli' : '';
        $nowatcher = $this->getLib('watcher') === null ? ',nowatcher' : '';
        $xcaddyModules = getenv('SPC_CMD_VAR_FRANKENPHP_XCADDY_MODULES');
        $frankenphpSourceDir = getenv('FRANKENPHP_SOURCE_PATH') ?: SOURCE_PATH . '/frankenphp';

        $xcaddyModules = preg_replace('#--with github.com/dunglas/frankenphp\S*#', '', $xcaddyModules);
        $xcaddyModules = "--with github.com/dunglas/frankenphp={$frankenphpSourceDir} " .
            "--with github.com/dunglas/frankenphp/caddy={$frankenphpSourceDir}/caddy {$xcaddyModules}";
        if ($this->getLib('brotli') === null && str_contains($xcaddyModules, '--with github.com/dunglas/caddy-cbrotli')) {
            logger()->warning('caddy-cbrotli module is enabled, but brotli library is not built. Disabling caddy-cbrotli.');
            $xcaddyModules = str_replace('--with github.com/dunglas/caddy-cbrotli', '', $xcaddyModules);
        }

        $frankenPhpVersion = $this->getFrankenPHPVersion();
        $libphpVersion = $this->getPHPVersion();
        $dynamic_exports = '';
        if (getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') === 'shared') {
            $libphpVersion = preg_replace('/\.\d+$/', '', $libphpVersion);
        } else {
            if ($dynamicSymbolsArgument = LinuxSystemUtil::getDynamicExportedSymbols(BUILD_LIB_PATH . '/libphp.a')) {
                $dynamic_exports = ' ' . $dynamicSymbolsArgument;
            }
        }
        $extLdFlags = "-extldflags '-pie{$dynamic_exports} {$this->arch_ld_flags}'";
        $muslTags = '';
        $staticFlags = '';
        if (SPCTarget::isStatic()) {
            $extLdFlags = "-extldflags '-static-pie -Wl,-z,stack-size=0x80000{$dynamic_exports} {$this->arch_ld_flags}'";
            $muslTags = 'static_build,';
            $staticFlags = '-static-pie';
        }

        $config = (new SPCConfigUtil($this))->config($this->ext_list, $this->lib_list);
        $cflags = "{$this->arch_c_flags} {$config['cflags']} " . getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') . ' -DFRANKENPHP_VERSION=' . $frankenPhpVersion;
        $libs = $config['libs'];
        // Go's gcc driver doesn't automatically link against -lgcov or -lrt. Ugly, but necessary fix.
        if ((str_contains((string) getenv('SPC_DEFAULT_C_FLAGS'), '-fprofile') ||
                str_contains((string) getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS'), '-fprofile')) &&
            ToolchainManager::getToolchainClass() === GccNativeToolchain::class) {
            $cflags .= ' -Wno-error=missing-profile';
            $libs .= ' -lgcov';
        }
        $env = [...[
            'CGO_ENABLED' => '1',
            'CGO_CFLAGS' => clean_spaces($cflags),
            'CGO_LDFLAGS' => "{$this->arch_ld_flags} {$staticFlags} {$config['ldflags']} {$libs}",
            'XCADDY_GO_BUILD_FLAGS' => '-buildmode=pie ' .
                '-ldflags \"-linkmode=external ' . $extLdFlags . ' ' .
                '-X \'github.com/caddyserver/caddy/v2.CustomVersion=FrankenPHP ' .
                "v{$frankenPhpVersion} PHP {$libphpVersion} Caddy'\\\" " .
                "-tags={$muslTags}nobadger,nomysql,nopgx{$nobrotli}{$nowatcher}",
            'LD_LIBRARY_PATH' => BUILD_LIB_PATH,
        ], ...GoXcaddy::getEnvironment()];
        shell()->cd(BUILD_BIN_PATH)
            ->setEnv($env)
            ->exec("xcaddy build --output frankenphp {$xcaddyModules}");

        $this->deploySAPIBinary(BUILD_TARGET_FRANKENPHP);
    }

    /**
     * Seek php-src/config.log when building PHP, add it to exception.
     */
    protected function seekPhpSrcLogFileOnException(callable $callback): void
    {
        try {
            $callback();
        } catch (SPCException $e) {
            if (file_exists(SOURCE_PATH . '/php-src/config.log')) {
                $e->addExtraLogFile('php-src config.log', 'php-src.config.log');
                copy(SOURCE_PATH . '/php-src/config.log', SPC_LOGS_DIR . '/php-src.config.log');
            }
            throw $e;
        }
    }
}
