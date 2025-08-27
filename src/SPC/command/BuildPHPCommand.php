<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\builder\BuilderProvider;
use SPC\exception\ExceptionHandler;
use SPC\store\Config;
use SPC\store\FileSystem;
use SPC\store\SourcePatcher;
use SPC\util\DependencyUtil;
use SPC\util\GlobalEnvManager;
use SPC\util\LicenseDumper;
use SPC\util\SPCTarget;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use ZM\Logger\ConsoleColor;

#[AsCommand('build', 'build PHP', ['build:php'])]
class BuildPHPCommand extends BuildCommand
{
    public function configure(): void
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';

        $this->addArgument('extensions', InputArgument::REQUIRED, 'The extensions will be compiled, comma separated');
        $this->addOption('with-libs', null, InputOption::VALUE_REQUIRED, 'add additional libraries, comma separated', '');
        $this->addOption('build-shared', 'D', InputOption::VALUE_REQUIRED, 'Shared extensions to build, comma separated', '');
        $this->addOption('build-micro', null, null, 'Build micro SAPI');
        $this->addOption('build-cli', null, null, 'Build cli SAPI');
        $this->addOption('build-fpm', null, null, 'Build fpm SAPI (not available on Windows)');
        $this->addOption('build-embed', null, null, 'Build embed SAPI (not available on Windows)');
        $this->addOption('build-frankenphp', null, null, 'Build FrankenPHP SAPI (not available on Windows)');
        $this->addOption('build-all', null, null, 'Build all SAPI');
        $this->addOption('no-strip', null, null, 'build without strip, keep symbols to debug');
        $this->addOption('disable-opcache-jit', null, null, 'disable opcache jit');
        $this->addOption('with-config-file-path', null, InputOption::VALUE_REQUIRED, 'Set the path in which to look for php.ini', $isWindows ? null : '/usr/local/etc/php');
        $this->addOption('with-config-file-scan-dir', null, InputOption::VALUE_REQUIRED, 'Set the directory to scan for .ini files after reading php.ini', $isWindows ? null : '/usr/local/etc/php/conf.d');
        $this->addOption('with-hardcoded-ini', 'I', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Patch PHP source code, inject hardcoded INI');
        $this->addOption('with-micro-fake-cli', null, null, 'Let phpmicro\'s PHP_SAPI use "cli" instead of "micro"');
        $this->addOption('with-suggested-libs', 'L', null, 'Build with suggested libs for selected exts and libs');
        $this->addOption('with-suggested-exts', 'E', null, 'Build with suggested extensions for selected exts');
        $this->addOption('with-added-patch', 'P', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Inject patch script outside');
        $this->addOption('without-micro-ext-test', null, null, 'Disable phpmicro with extension test code');
        $this->addOption('with-upx-pack', null, null, 'Compress / pack binary using UPX tool (linux/windows only)');
        $this->addOption('with-micro-logo', null, InputOption::VALUE_REQUIRED, 'Use custom .ico for micro.sfx (windows only)');
        $this->addOption('enable-micro-win32', null, null, 'Enable win32 mode for phpmicro (Windows only)');
    }

    public function handle(): int
    {
        // transform string to array
        $libraries = array_map('trim', array_filter(explode(',', $this->getOption('with-libs'))));
        // transform string to array
        $shared_extensions = array_map('trim', array_filter(explode(',', $this->getOption('build-shared'))));
        // transform string to array
        $static_extensions = $this->parseExtensionList($this->getArgument('extensions'));

        // parse rule with options
        $rule = $this->parseRules($shared_extensions);

        // check dynamic extension build env
        // linux must build with glibc
        if (!empty($shared_extensions) && SPCTarget::isStatic()) {
            $this->output->writeln('Linux does not support dynamic extension loading with fully static builds, please build with a shared C runtime target!');
            return static::FAILURE;
        }
        $static_and_shared = array_intersect($static_extensions, $shared_extensions);
        if (!empty($static_and_shared)) {
            $this->output->writeln('<comment>Building extensions [' . implode(',', $static_and_shared) . '] as both static and shared, tests may not be accurate or fail.</comment>');
        }

        if ($rule === BUILD_TARGET_NONE) {
            $this->output->writeln('<error>Please add at least one build SAPI!</error>');
            $this->output->writeln("<comment>\t--build-cli\t\tBuild php-cli SAPI</comment>");
            $this->output->writeln("<comment>\t--build-micro\t\tBuild phpmicro SAPI</comment>");
            $this->output->writeln("<comment>\t--build-fpm\t\tBuild php-fpm SAPI</comment>");
            $this->output->writeln("<comment>\t--build-embed\t\tBuild embed SAPI/libphp</comment>");
            $this->output->writeln("<comment>\t--build-frankenphp\tBuild FrankenPHP SAPI/libphp</comment>");
            $this->output->writeln("<comment>\t--build-all\t\tBuild all SAPI: cli, micro, fpm, embed, frankenphp</comment>");
            return static::FAILURE;
        }
        if ($rule === BUILD_TARGET_ALL) {
            logger()->warning('--build-all option makes `--no-strip` always true, be aware!');
        }
        if (($rule & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO && $this->getOption('with-micro-logo')) {
            $logo = $this->getOption('with-micro-logo');
            if (!file_exists($logo)) {
                logger()->error('Logo file ' . $logo . ' not exist !');
                return static::FAILURE;
            }
        }

        // Check upx
        $suffix = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
        if ($this->getOption('with-upx-pack')) {
            // only available for linux for now
            if (!in_array(PHP_OS_FAMILY, ['Linux', 'Windows'])) {
                logger()->error('UPX is only available on Linux and Windows!');
                return static::FAILURE;
            }
            // need to install this manually
            if (!file_exists(PKG_ROOT_PATH . '/bin/upx' . $suffix)) {
                global $argv;
                logger()->error('upx does not exist, please install it first:');
                logger()->error('');
                logger()->error("\t" . $argv[0] . ' install-pkg upx');
                logger()->error('');
                return static::FAILURE;
            }
            // exclusive with no-strip
            if ($this->getOption('no-strip')) {
                logger()->warning('--with-upx-pack conflicts with --no-strip, --no-strip won\'t work!');
            }
            if (($rule & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO) {
                logger()->warning('Some cases micro.sfx cannot be packed via UPX due to dynamic size bug, be aware!');
            }
        }
        // create builder
        $builder = BuilderProvider::makeBuilderByInput($this->input);
        $include_suggest_ext = $this->getOption('with-suggested-exts');
        $include_suggest_lib = $this->getOption('with-suggested-libs');
        [$extensions, $libraries, $not_included] = DependencyUtil::getExtsAndLibs(array_merge($static_extensions, $shared_extensions), $libraries, $include_suggest_ext, $include_suggest_lib);
        $display_libs = array_filter($libraries, fn ($lib) => in_array(Config::getLib($lib, 'type', 'lib'), ['lib', 'package']));

        // separate static and shared extensions from $extensions
        // filter rule: including shared extensions if they are in $static_extensions or $shared_extensions
        $static_extensions = array_filter($extensions, fn ($ext) => !in_array($ext, $shared_extensions) || in_array($ext, $static_extensions));

        // print info
        $indent_texts = [
            'Build OS' => PHP_OS_FAMILY . ' (' . php_uname('m') . ')',
            'Build Target' => getenv('SPC_TARGET'),
            'Build Toolchain' => getenv('SPC_TOOLCHAIN'),
            'Build SAPI' => $builder->getBuildTypeName($rule),
            'Static Extensions (' . count($static_extensions) . ')' => implode(',', $static_extensions),
            'Shared Extensions (' . count($shared_extensions) . ')' => implode(',', $shared_extensions),
            'Libraries (' . count($libraries) . ')' => implode(',', $display_libs),
            'Strip Binaries' => $builder->getOption('no-strip') ? 'no' : 'yes',
            'Enable ZTS' => $builder->getOption('enable-zts') ? 'yes' : 'no',
        ];
        if (!empty($shared_extensions) || ($rule & BUILD_TARGET_EMBED)) {
            $indent_texts['Build Dev'] = 'yes';
        }
        if (!empty($this->input->getOption('with-config-file-path'))) {
            $indent_texts['Config File Path'] = $this->input->getOption('with-config-file-path');
        }
        if (!empty($this->input->getOption('with-hardcoded-ini'))) {
            $indent_texts['Hardcoded INI'] = $this->input->getOption('with-hardcoded-ini');
        }
        if ($this->input->getOption('disable-opcache-jit')) {
            $indent_texts['Opcache JIT'] = 'disabled';
        }
        if ($this->input->getOption('with-upx-pack') && in_array(PHP_OS_FAMILY, ['Linux', 'Windows'])) {
            $indent_texts['UPX Pack'] = 'enabled';
        }

        $ver = $builder->getPHPVersionFromArchive() ?: $builder->getPHPVersion(false);
        $indent_texts['PHP Version'] = $ver;

        if (!empty($not_included)) {
            $indent_texts['Extra Exts (' . count($not_included) . ')'] = implode(', ', $not_included);
        }
        $this->printFormatInfo($this->getDefinedEnvs(), true);
        $this->printFormatInfo($indent_texts);

        // bind extra info to exception handler
        ExceptionHandler::bindBuildPhpExtraInfo($indent_texts);

        logger()->notice('Build will start after 2s ...');
        sleep(2);

        // compile libraries
        $builder->proveLibs($libraries);
        // check extensions
        $builder->proveExts($static_extensions, $shared_extensions);
        // validate libs and extensions
        $builder->validateLibsAndExts();

        // check some things before building all the things
        $builder->checkBeforeBuildPHP($rule);

        // clean builds and sources
        if ($this->input->getOption('with-clean')) {
            logger()->info('Cleaning source and previous build dir...');
            FileSystem::removeDir(SOURCE_PATH);
            FileSystem::removeDir(BUILD_ROOT_PATH);
        }

        // build or install libraries
        $builder->setupLibs();

        // Process -I option
        $custom_ini = [];
        foreach ($this->input->getOption('with-hardcoded-ini') as $value) {
            [$source_name, $ini_value] = explode('=', $value, 2);
            $custom_ini[$source_name] = $ini_value;
            logger()->info('Adding hardcoded INI [' . $source_name . ' = ' . $ini_value . ']');
        }
        if (!empty($custom_ini)) {
            SourcePatcher::patchHardcodedINI($custom_ini);
        }

        // add static-php-cli.version to main.c, in order to debug php failure more easily
        SourcePatcher::patchSPCVersionToPHP($this->getApplication()->getVersion());

        // clean old modules that may conflict with the new php build
        FileSystem::removeDir(BUILD_MODULES_PATH);
        // start to build
        $builder->buildPHP($rule);

        // build dynamic extensions if needed
        if (!empty($shared_extensions)) {
            logger()->info('Building shared extensions ...');
            $builder->buildSharedExts();
        }

        $builder->testPHP($rule);

        // compile stopwatch :P
        $time = round(microtime(true) - START_TIME, 3);
        logger()->info('');
        logger()->info('   Build complete, used ' . $time . ' s !');
        logger()->info('');

        // ---------- When using bin/spc-alpine-docker, the build root path is different from the host system ----------
        $build_root_path = BUILD_ROOT_PATH;
        $cwd = getcwd();
        $fixed = '';
        if (!empty(getenv('SPC_FIX_DEPLOY_ROOT'))) {
            str_replace($cwd, '', $build_root_path);
            $build_root_path = getenv('SPC_FIX_DEPLOY_ROOT') . '/' . basename($build_root_path);
            $fixed = ' (host system)';
        }
        if (($rule & BUILD_TARGET_CLI) === BUILD_TARGET_CLI) {
            $win_suffix = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
            $path = FileSystem::convertPath("{$build_root_path}/bin/php{$win_suffix}");
            logger()->info("Static php binary path{$fixed}: {$path}");
        }
        if (($rule & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO) {
            $path = FileSystem::convertPath("{$build_root_path}/bin/micro.sfx");
            logger()->info("phpmicro binary path{$fixed}: {$path}");
        }
        if (($rule & BUILD_TARGET_FPM) === BUILD_TARGET_FPM && PHP_OS_FAMILY !== 'Windows') {
            $path = FileSystem::convertPath("{$build_root_path}/bin/php-fpm");
            logger()->info("Static php-fpm binary path{$fixed}: {$path}");
        }
        if (!empty($shared_extensions)) {
            foreach ($shared_extensions as $ext) {
                $path = FileSystem::convertPath("{$build_root_path}/modules/{$ext}.so");
                if (file_exists(BUILD_MODULES_PATH . "/{$ext}.so")) {
                    logger()->info("Shared extension [{$ext}] path{$fixed}: {$path}");
                } elseif (Config::getExt($ext, 'type') !== 'addon') {
                    logger()->warning("Shared extension [{$ext}] not found, please check!");
                }
            }
        }

        // export metadata
        file_put_contents(BUILD_ROOT_PATH . '/build-extensions.json', json_encode($extensions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        file_put_contents(BUILD_ROOT_PATH . '/build-libraries.json', json_encode($libraries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        // export licenses
        $dumper = new LicenseDumper();
        $dumper->addExts($extensions)->addLibs($libraries)->addSources(['php-src'])->dump(BUILD_ROOT_PATH . '/license');
        $path = FileSystem::convertPath("{$build_root_path}/license/");
        logger()->info("License path{$fixed}: {$path}");
        return static::SUCCESS;
    }

    /**
     * Parse build options to rule int.
     */
    private function parseRules(array $shared_extensions = []): int
    {
        $rule = BUILD_TARGET_NONE;
        $rule |= ($this->getOption('build-cli') ? BUILD_TARGET_CLI : BUILD_TARGET_NONE);
        $rule |= ($this->getOption('build-micro') ? BUILD_TARGET_MICRO : BUILD_TARGET_NONE);
        $rule |= ($this->getOption('build-fpm') ? BUILD_TARGET_FPM : BUILD_TARGET_NONE);
        $rule |= $this->getOption('build-embed') || !empty($shared_extensions) ? BUILD_TARGET_EMBED : BUILD_TARGET_NONE;
        $rule |= ($this->getOption('build-frankenphp') ? (BUILD_TARGET_FRANKENPHP | BUILD_TARGET_EMBED) : BUILD_TARGET_NONE);
        $rule |= ($this->getOption('build-all') ? BUILD_TARGET_ALL : BUILD_TARGET_NONE);
        return $rule;
    }

    private function getDefinedEnvs(): array
    {
        $envs = GlobalEnvManager::getInitializedEnv();
        $final = [];
        foreach ($envs as $env) {
            $exp = explode('=', $env, 2);
            $final['Init var [' . $exp[0] . ']'] = $exp[1];
        }
        return $final;
    }

    private function printFormatInfo(array $indent_texts, bool $debug = false): void
    {
        // calculate space count for every line
        $maxlen = 0;
        foreach ($indent_texts as $k => $v) {
            $maxlen = max(strlen($k), $maxlen);
        }
        foreach ($indent_texts as $k => $v) {
            if (is_string($v)) {
                /* @phpstan-ignore-next-line */
                logger()->{$debug ? 'debug' : 'info'}($k . ': ' . str_pad('', $maxlen - strlen($k)) . ConsoleColor::yellow($v));
            } elseif (is_array($v) && !is_assoc_array($v)) {
                $first = array_shift($v);
                /* @phpstan-ignore-next-line */
                logger()->{$debug ? 'debug' : 'info'}($k . ': ' . str_pad('', $maxlen - strlen($k)) . ConsoleColor::yellow($first));
                foreach ($v as $vs) {
                    /* @phpstan-ignore-next-line */
                    logger()->{$debug ? 'debug' : 'info'}(str_pad('', $maxlen + 2) . ConsoleColor::yellow($vs));
                }
            }
        }
    }
}
