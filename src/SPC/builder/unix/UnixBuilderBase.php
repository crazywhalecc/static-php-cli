<?php

declare(strict_types=1);

namespace SPC\builder\unix;

use SPC\builder\BuilderBase;
use SPC\exception\SPCInternalException;
use SPC\exception\ValidationException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\CurlHook;
use SPC\store\Downloader;
use SPC\store\FileSystem;
use SPC\store\pkg\GoXcaddy;
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
            // if someone changed to EMBED_TYPE=shared, we need to add LD_LIBRARY_PATH
            if (getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') === 'shared') {
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
            }
            [$ret, $out] = shell()->cd($sample_file_path)->execWithResult(getenv('CC') . ' -o embed embed.c ' . $lens);
            if ($ret !== 0) {
                throw new ValidationException(
                    'embed failed sanity check: build failed. Error message: ' . implode("\n", $out),
                    validation_module: 'static libphp.a sanity check'
                );
            }
            [$ret, $output] = shell()->cd($sample_file_path)->execWithResult($ext_path . './embed');
            if ($ret !== 0 || trim(implode('', $output)) !== 'hello') {
                throw new ValidationException(
                    'embed failed sanity check: run failed. Error message: ' . implode("\n", $output),
                    validation_module: 'static libphp.a sanity check'
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
     * Deploy the binary file to the build bin path.
     *
     * @param int $type Type integer, one of BUILD_TARGET_CLI, BUILD_TARGET_MICRO, BUILD_TARGET_FPM
     */
    protected function deployBinary(int $type): bool
    {
        $src = match ($type) {
            BUILD_TARGET_CLI => SOURCE_PATH . '/php-src/sapi/cli/php',
            BUILD_TARGET_MICRO => SOURCE_PATH . '/php-src/sapi/micro/micro.sfx',
            BUILD_TARGET_FPM => SOURCE_PATH . '/php-src/sapi/fpm/php-fpm',
            default => throw new SPCInternalException("Deployment does not accept type {$type}"),
        };
        logger()->info('Deploying ' . $this->getBuildTypeName($type) . ' file');
        FileSystem::createDir(BUILD_BIN_PATH);
        shell()->exec('cp ' . escapeshellarg($src) . ' ' . escapeshellarg(BUILD_BIN_PATH));
        return true;
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

    protected function buildFrankenphp(): void
    {
        $nobrotli = $this->getLib('brotli') === null ? ',nobrotli' : '';
        $nowatcher = $this->getLib('watcher') === null ? ',nowatcher' : '';
        $xcaddyModules = getenv('SPC_CMD_VAR_FRANKENPHP_XCADDY_MODULES');
        // make it possible to build from a different frankenphp directory!
        if (!str_contains($xcaddyModules, '--with github.com/dunglas/frankenphp')) {
            $xcaddyModules = '--with github.com/dunglas/frankenphp ' . $xcaddyModules;
        }
        if ($this->getLib('brotli') === null && str_contains($xcaddyModules, '--with github.com/dunglas/caddy-cbrotli')) {
            logger()->warning('caddy-cbrotli module is enabled, but brotli library is not built. Disabling caddy-cbrotli.');
            $xcaddyModules = str_replace('--with github.com/dunglas/caddy-cbrotli', '', $xcaddyModules);
        }
        $lrt = PHP_OS_FAMILY === 'Linux' ? '-lrt' : '';
        $releaseInfo = json_decode(Downloader::curlExec(
            'https://api.github.com/repos/php/frankenphp/releases/latest',
            hooks: [[CurlHook::class, 'setupGithubToken']],
        ), true);
        $frankenPhpVersion = $releaseInfo['tag_name'];
        $libphpVersion = $this->getPHPVersion();
        if (getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') === 'shared') {
            $libphpVersion = preg_replace('/\.\d$/', '', $libphpVersion);
        }
        $debugFlags = $this->getOption('no-strip') ? '-w -s ' : '';
        $extLdFlags = "-extldflags '-pie'";
        $muslTags = '';
        $staticFlags = '';
        if (SPCTarget::isStatic()) {
            $extLdFlags = "-extldflags '-static-pie -Wl,-z,stack-size=0x80000'";
            $muslTags = 'static_build,';
            $staticFlags = '-static-pie';
        }

        $config = (new SPCConfigUtil($this))->config($this->ext_list, $this->lib_list);
        $env = [
            'CGO_ENABLED' => '1',
            'CGO_CFLAGS' => $this->arch_c_flags . ' ' . $config['cflags'],
            'CGO_LDFLAGS' => "{$this->arch_ld_flags} {$staticFlags} {$config['ldflags']} {$config['libs']} {$lrt}",
            'XCADDY_GO_BUILD_FLAGS' => '-buildmode=pie ' .
                '-ldflags \"-linkmode=external ' . $extLdFlags . ' ' . $debugFlags .
                '-X \'github.com/caddyserver/caddy/v2.CustomVersion=FrankenPHP ' .
                "{$frankenPhpVersion} PHP {$libphpVersion} Caddy'\\\" " .
                "-tags={$muslTags}nobadger,nomysql,nopgx{$nobrotli}{$nowatcher}",
            'LD_LIBRARY_PATH' => BUILD_LIB_PATH,
        ];
        foreach (GoXcaddy::getEnvironment() as $key => $value) {
            if ($key === 'PATH') {
                GlobalEnvManager::addPathIfNotExists($value);
            } else {
                $env[$key] = $value;
            }
        }
        shell()->cd(BUILD_BIN_PATH)
            ->setEnv($env)
            ->exec("xcaddy build --output frankenphp {$xcaddyModules}");

        if (!$this->getOption('no-strip', false) && file_exists(BUILD_BIN_PATH . '/frankenphp')) {
            if (PHP_OS_FAMILY === 'Linux') {
                shell()->cd(BUILD_BIN_PATH)->exec('strip --strip-unneeded frankenphp');
            } else { // macOS doesn't understand strip-unneeded
                shell()->cd(BUILD_BIN_PATH)->exec('strip -S frankenphp');
            }
        }
    }
}
