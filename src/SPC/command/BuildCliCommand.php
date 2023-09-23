<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\builder\BuilderProvider;
use SPC\exception\ExceptionHandler;
use SPC\exception\WrongUsageException;
use SPC\store\SourcePatcher;
use SPC\util\DependencyUtil;
use SPC\util\LicenseDumper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use ZM\Logger\ConsoleColor;

#[AsCommand('build', 'build CLI binary')]
class BuildCliCommand extends BuildCommand
{
    public function configure(): void
    {
        $this->addArgument('extensions', InputArgument::REQUIRED, 'The extensions will be compiled, comma separated');
        $this->addOption('with-libs', null, InputOption::VALUE_REQUIRED, 'add additional libraries, comma separated', '');
        $this->addOption('build-micro', null, null, 'build micro');
        $this->addOption('build-cli', null, null, 'build cli');
        $this->addOption('build-fpm', null, null, 'build fpm');
        $this->addOption('build-embed', null, null, 'build embed');
        $this->addOption('build-all', null, null, 'build cli, micro, fpm, embed');
        $this->addOption('no-strip', null, null, 'build without strip, in order to debug and load external extensions');
        $this->addOption('enable-zts', null, null, 'enable ZTS support');
        $this->addOption('with-hardcoded-ini', 'I', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Patch PHP source code, inject hardcoded INI');
        $this->addOption('with-micro-fake-cli', null, null, 'Enable phpmicro fake cli');
    }

    public function handle(): int
    {
        // transform string to array
        $libraries = array_map('trim', array_filter(explode(',', $this->getOption('with-libs'))));
        // transform string to array
        $extensions = array_map('trim', array_filter(explode(',', $this->getArgument('extensions'))));

        $rule = BUILD_TARGET_NONE;
        $rule |= ($this->getOption('build-cli') ? BUILD_TARGET_CLI : BUILD_TARGET_NONE);
        $rule |= ($this->getOption('build-micro') ? BUILD_TARGET_MICRO : BUILD_TARGET_NONE);
        $rule |= ($this->getOption('build-fpm') ? BUILD_TARGET_FPM : BUILD_TARGET_NONE);
        $rule |= ($this->getOption('build-embed') ? BUILD_TARGET_EMBED : BUILD_TARGET_NONE);
        $rule |= ($this->getOption('build-all') ? BUILD_TARGET_ALL : BUILD_TARGET_NONE);
        if ($rule === BUILD_TARGET_NONE) {
            $this->output->writeln('<error>Please add at least one build target!</error>');
            $this->output->writeln("<comment>\t--build-cli\tBuild php-cli SAPI</comment>");
            $this->output->writeln("<comment>\t--build-micro\tBuild phpmicro SAPI</comment>");
            $this->output->writeln("<comment>\t--build-fpm\tBuild php-fpm SAPI</comment>");
            $this->output->writeln("<comment>\t--build-embed\tBuild embed SAPI/libphp</comment>");
            $this->output->writeln("<comment>\t--build-all\tBuild all SAPI: cli, micro, fpm, embed</comment>");
            return static::FAILURE;
        }
        try {
            // create builder
            $builder = BuilderProvider::makeBuilderByInput($this->input);
            // calculate dependencies
            [$extensions, $libraries, $not_included] = DependencyUtil::getExtLibsByDeps($extensions, $libraries);
            /* @phpstan-ignore-next-line */
            logger()->info('Build target: ' . ConsoleColor::yellow($builder->getBuildTypeName($rule)));
            /* @phpstan-ignore-next-line */
            logger()->info('Enabled extensions: ' . ConsoleColor::yellow(implode(', ', $extensions)));
            /* @phpstan-ignore-next-line */
            logger()->info('Required libraries: ' . ConsoleColor::yellow(implode(', ', $libraries)));
            if (!empty($not_included)) {
                logger()->warning('some extensions will be enabled due to dependencies: ' . implode(',', $not_included));
            }
            sleep(2);
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
                logger()->info('Static php binary path' . $fixed . ': ' . $build_root_path . '/bin/php');
            }
            if (($rule & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO) {
                logger()->info('phpmicro binary path' . $fixed . ': ' . $build_root_path . '/bin/micro.sfx');
            }
            if (($rule & BUILD_TARGET_FPM) === BUILD_TARGET_FPM) {
                logger()->info('Static php-fpm binary path' . $fixed . ': ' . $build_root_path . '/bin/php-fpm');
            }

            // export metadata
            file_put_contents(BUILD_ROOT_PATH . '/build-extensions.json', json_encode($extensions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            file_put_contents(BUILD_ROOT_PATH . '/build-libraries.json', json_encode($libraries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            // export licenses
            $dumper = new LicenseDumper();
            $dumper->addExts($extensions)->addLibs($libraries)->addSources(['php-src'])->dump(BUILD_ROOT_PATH . '/license');
            logger()->info('License path' . $fixed . ': ' . $build_root_path . '/license/');
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
}
