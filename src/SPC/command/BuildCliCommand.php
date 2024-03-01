<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\builder\BuilderProvider;
use SPC\exception\ExceptionHandler;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
use SPC\store\SourcePatcher;
use SPC\util\DependencyUtil;
use SPC\util\LicenseDumper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use ZM\Logger\ConsoleColor;

#[AsCommand('build', 'build PHP')]
class BuildCliCommand extends BuildCommand
{
    public function configure(): void
    {
        $this->addArgument('extensions', InputArgument::REQUIRED, 'The extensions will be compiled, comma separated');
        $this->addOption('with-libs', null, InputOption::VALUE_REQUIRED, 'add additional libraries, comma separated', '');
        $this->addOption('build-micro', null, null, 'Build micro SAPI');
        $this->addOption('build-cli', null, null, 'Build cli SAPI');
        $this->addOption('build-fpm', null, null, 'Build fpm SAPI');
        $this->addOption('build-embed', null, null, 'Build embed SAPI');
        $this->addOption('build-all', null, null, 'Build all SAPI');
        $this->addOption('no-strip', null, null, 'build without strip, in order to debug and load external extensions');
        $this->addOption('enable-zts', null, null, 'enable ZTS support');
        $this->addOption('disable-opcache-jit', null, null, 'disable opcache jit');
        $this->addOption('with-hardcoded-ini', 'I', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Patch PHP source code, inject hardcoded INI');
        $this->addOption('with-micro-fake-cli', null, null, 'Let phpmicro\'s PHP_SAPI use "cli" instead of "micro"');
        $this->addOption('with-suggested-libs', 'L', null, 'Build with suggested libs for selected exts and libs');
        $this->addOption('with-suggested-exts', 'E', null, 'Build with suggested extensions for selected exts');
        $this->addOption('with-added-patch', 'P', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Inject patch script outside');
        $this->addOption('without-micro-ext-test', null, null, 'Disable phpmicro with extension test code');
        $this->addOption('with-upx-pack', null, null, 'Compress / pack binary using UPX tool (linux/windows only)');
        $this->addOption('with-micro-logo', null, InputOption::VALUE_REQUIRED, 'Use custom .ico for micro.sfx (windows only)');
    }

    public function handle(): int
    {
        // transform string to array
        $libraries = array_map('trim', array_filter(explode(',', $this->getOption('with-libs'))));
        // transform string to array
        $extensions = array_map('trim', array_filter(explode(',', $this->getArgument('extensions'))));

        // parse rule with options
        $rule = $this->parseRules();

        if ($rule === BUILD_TARGET_NONE) {
            $this->output->writeln('<error>Please add at least one build target!</error>');
            $this->output->writeln("<comment>\t--build-cli\tBuild php-cli SAPI</comment>");
            $this->output->writeln("<comment>\t--build-micro\tBuild phpmicro SAPI</comment>");
            $this->output->writeln("<comment>\t--build-fpm\tBuild php-fpm SAPI</comment>");
            $this->output->writeln("<comment>\t--build-embed\tBuild embed SAPI/libphp</comment>");
            $this->output->writeln("<comment>\t--build-all\tBuild all SAPI: cli, micro, fpm, embed</comment>");
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
        }
        try {
            // create builder
            $builder = BuilderProvider::makeBuilderByInput($this->input);
            $include_suggest_ext = $this->getOption('with-suggested-exts');
            $include_suggest_lib = $this->getOption('with-suggested-libs');
            [$extensions, $libraries, $not_included] = DependencyUtil::getExtsAndLibs($extensions, $libraries, $include_suggest_ext, $include_suggest_lib);

            // print info
            $indent_texts = [
                'Build OS' => PHP_OS_FAMILY . ' (' . php_uname('m') . ')',
                'Build SAPI' => $builder->getBuildTypeName($rule),
                'Extensions (' . count($extensions) . ')' => implode(', ', $extensions),
                'Libraries (' . count($libraries) . ')' => implode(', ', $libraries),
                'Strip Binaries' => $builder->getOption('no-strip') ? 'no' : 'yes',
                'Enable ZTS' => $builder->getOption('enable-zts') ? 'yes' : 'no',
            ];
            if (!empty($this->input->getOption('with-hardcoded-ini'))) {
                $indent_texts['Hardcoded INI'] = $this->input->getOption('with-hardcoded-ini');
            }
            if ($this->input->getOption('disable-opcache-jit')) {
                $indent_texts['Opcache JIT'] = 'disabled';
            }
            if ($this->input->getOption('with-upx-pack') && in_array(PHP_OS_FAMILY, ['Linux', 'Windows'])) {
                $indent_texts['UPX Pack'] = 'enabled';
                $builder->setOption('upx-exec', FileSystem::convertPath(PKG_ROOT_PATH . '/bin/upx' . $suffix));
            }
            try {
                $ver = $builder->getPHPVersion();
                $indent_texts['PHP Version'] = $ver;
            } catch (\Throwable) {
                if (($ver = $builder->getPHPVersionFromArchive()) !== false) {
                    $indent_texts['PHP Version'] = $ver;
                }
            }

            if (!empty($not_included)) {
                $indent_texts['Extra Exts (' . count($not_included) . ')'] = implode(', ', $not_included);
            }
            $this->printFormatInfo($indent_texts);
            logger()->notice('Build will start after 2s ...');
            sleep(2);

            if ($this->input->getOption('with-clean')) {
                logger()->info('Cleaning source dir...');
                FileSystem::removeDir(SOURCE_PATH);
            }
            // compile libraries
            $builder->buildLibs($libraries);
            // check extensions
            $builder->proveExts($extensions);

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

            // start to build
            $builder->buildPHP($rule);

            // compile stopwatch :P
            $time = round(microtime(true) - START_TIME, 3);
            logger()->info('Build complete, used ' . $time . ' s !');

            // ---------- When using bin/spc-alpine-docker, the build root path is different from the host system ----------
            $build_root_path = BUILD_ROOT_PATH;
            $cwd = getcwd();
            $fixed = '';
            if (!empty(getenv('SPC_FIX_DEPLOY_ROOT'))) {
                str_replace($cwd, '', $build_root_path);
                $build_root_path = getenv('SPC_FIX_DEPLOY_ROOT') . $build_root_path;
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

            // export metadata
            file_put_contents(BUILD_ROOT_PATH . '/build-extensions.json', json_encode($extensions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            file_put_contents(BUILD_ROOT_PATH . '/build-libraries.json', json_encode($libraries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            // export licenses
            $dumper = new LicenseDumper();
            $dumper->addExts($extensions)->addLibs($libraries)->addSources(['php-src'])->dump(BUILD_ROOT_PATH . '/license');
            $path = FileSystem::convertPath("{$build_root_path}/license/");
            logger()->info("License path{$fixed}: {$path}");
            return static::SUCCESS;
        } catch (WrongUsageException $e) {
            // WrongUsageException is not an exception, it's a user error, so we just print the error message
            logger()->critical($e->getMessage());
            return static::FAILURE;
        } catch (\Throwable $e) {
            if ($this->getOption('debug')) {
                ExceptionHandler::getInstance()->handle($e);
            } else {
                logger()->critical('Build failed with ' . get_class($e) . ': ' . $e->getMessage());
                logger()->critical('Please check with --debug option to see more details.');
            }
            return static::FAILURE;
        }
    }

    /**
     * Parse build options to rule int.
     */
    private function parseRules(): int
    {
        $rule = BUILD_TARGET_NONE;
        $rule |= ($this->getOption('build-cli') ? BUILD_TARGET_CLI : BUILD_TARGET_NONE);
        $rule |= ($this->getOption('build-micro') ? BUILD_TARGET_MICRO : BUILD_TARGET_NONE);
        $rule |= ($this->getOption('build-fpm') ? BUILD_TARGET_FPM : BUILD_TARGET_NONE);
        $rule |= ($this->getOption('build-embed') ? BUILD_TARGET_EMBED : BUILD_TARGET_NONE);
        $rule |= ($this->getOption('build-all') ? BUILD_TARGET_ALL : BUILD_TARGET_NONE);
        return $rule;
    }

    private function printFormatInfo(array $indent_texts): void
    {
        // calculate space count for every line
        $maxlen = 0;
        foreach ($indent_texts as $k => $v) {
            $maxlen = max(strlen($k), $maxlen);
        }
        foreach ($indent_texts as $k => $v) {
            if (is_string($v)) {
                /* @phpstan-ignore-next-line */
                logger()->info($k . ': ' . str_pad('', $maxlen - strlen($k)) . ConsoleColor::yellow($v));
            } elseif (is_array($v) && !is_assoc_array($v)) {
                $first = array_shift($v);
                /* @phpstan-ignore-next-line */
                logger()->info($k . ': ' . str_pad('', $maxlen - strlen($k)) . ConsoleColor::yellow($first));
                foreach ($v as $vs) {
                    /* @phpstan-ignore-next-line */
                    logger()->info(str_pad('', $maxlen + 2) . ConsoleColor::yellow($vs));
                }
            }
        }
    }
}
