<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\builder\BuilderProvider;
use SPC\exception\ExceptionHandler;
use SPC\exception\WrongUsageException;
use SPC\util\DependencyUtil;
use SPC\util\LicenseDumper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use ZM\Logger\ConsoleColor;

#[AsCommand('build', 'build CLI binary')]
class BuildCliCommand extends BuildCommand
{
    public function configure()
    {
        $this->addArgument('extensions', InputArgument::REQUIRED, 'The extensions will be compiled, comma separated');
        $this->addOption('with-libs', null, InputOption::VALUE_REQUIRED, 'add additional libraries, comma separated', '');
        $this->addOption('build-micro', null, null, 'build micro');
        $this->addOption('build-cli', null, null, 'build cli');
        $this->addOption('build-fpm', null, null, 'build fpm');
        $this->addOption('build-all', null, null, 'build cli, micro, fpm');
        $this->addOption('no-strip', null, null, 'build without strip, in order to debug and load external extensions');
        $this->addOption('enable-zts', null, null, 'enable ZTS support');
    }

    public function handle(): int
    {
        // 从参数中获取要编译的 libraries，并转换为数组
        $libraries = array_map('trim', array_filter(explode(',', $this->getOption('with-libs'))));
        // 从参数中获取要编译的 extensions，并转换为数组
        $extensions = array_map('trim', array_filter(explode(',', $this->getArgument('extensions'))));

        define('BUILD_ALL_STATIC', true);

        $rule = BUILD_TARGET_NONE;
        $rule = $rule | ($this->getOption('build-cli') ? BUILD_TARGET_CLI : BUILD_TARGET_NONE);
        $rule = $rule | ($this->getOption('build-micro') ? BUILD_TARGET_MICRO : BUILD_TARGET_NONE);
        $rule = $rule | ($this->getOption('build-fpm') ? BUILD_TARGET_FPM : BUILD_TARGET_NONE);
        $rule = $rule | ($this->getOption('build-all') ? BUILD_TARGET_ALL : BUILD_TARGET_NONE);
        if ($rule === BUILD_TARGET_NONE) {
            $this->output->writeln('<error>Please add at least one build target!</error>');
            $this->output->writeln("<comment>\t--build-cli\tBuild php-cli SAPI</comment>");
            $this->output->writeln("<comment>\t--build-micro\tBuild phpmicro SAPI</comment>");
            $this->output->writeln("<comment>\t--build-fpm\tBuild php-fpm SAPI</comment>");
            $this->output->writeln("<comment>\t--build-all\tBuild all SAPI: cli, micro, fpm</comment>");
            return 1;
        }
        try {
            // 构建对象
            $builder = BuilderProvider::makeBuilderByInput($this->input);
            // 根据提供的扩展列表获取依赖库列表并编译
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
            // 编译和检查库是否完整
            $builder->buildLibs($libraries);
            // 执行扩展检测
            $builder->proveExts($extensions);
            // strip
            $builder->setStrip(false);
            // 构建
            $builder->buildPHP($rule, $this->getOption('bloat'));
            // 统计时间
            $time = round(microtime(true) - START_TIME, 3);
            logger()->info('Build complete, used ' . $time . ' s !');
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
            // 导出相关元数据
            file_put_contents(BUILD_ROOT_PATH . '/build-extensions.json', json_encode($extensions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            file_put_contents(BUILD_ROOT_PATH . '/build-libraries.json', json_encode($libraries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            // 导出 LICENSE
            $dumper = new LicenseDumper();
            $dumper->addExts($extensions)->addLibs($libraries)->addSources(['php-src'])->dump(BUILD_ROOT_PATH . '/license');
            logger()->info('License path' . $fixed . ': ' . $build_root_path . '/license/');
            return 0;
        } catch (WrongUsageException $e) {
            logger()->critical($e->getMessage());
            return 1;
        } catch (\Throwable $e) {
            if ($this->getOption('debug')) {
                ExceptionHandler::getInstance()->handle($e);
            } else {
                logger()->critical('Build failed with ' . get_class($e) . ': ' . $e->getMessage());
                logger()->critical('Please check with --debug option to see more details.');
            }
            return 1;
        }
    }
}
