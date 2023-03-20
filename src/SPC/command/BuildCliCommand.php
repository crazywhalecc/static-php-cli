<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\builder\BuilderProvider;
use SPC\exception\ExceptionHandler;
use SPC\util\DependencyUtil;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** @noinspection PhpUnused */
class BuildCliCommand extends BuildCommand
{
    protected static $defaultName = 'build';

    public function configure()
    {
        $this->setDescription('Build CLI binary');
        $this->addArgument('extensions', InputArgument::REQUIRED, 'The extensions will be compiled, comma separated');
        $this->addOption('with-libs', null, InputOption::VALUE_REQUIRED, 'add additional libraries, comma separated', '');
        $this->addOption('build-micro', null, null, 'build micro only');
        $this->addOption('build-all', null, null, 'build both cli and micro');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // 从参数中获取要编译的 libraries，并转换为数组
        $libraries = array_map('trim', array_filter(explode(',', $input->getOption('with-libs'))));
        // 从参数中获取要编译的 extensions，并转换为数组
        $extensions = array_map('trim', array_filter(explode(',', $input->getArgument('extensions'))));

        define('BUILD_ALL_STATIC', true);

        if ($input->getOption('build-all')) {
            $rule = BUILD_MICRO_BOTH;
            logger()->info('Builder will build php-cli and phpmicro SAPI');
        } elseif ($input->getOption('build-micro')) {
            $rule = BUILD_MICRO_ONLY;
            logger()->info('Builder will build phpmicro SAPI');
        } else {
            $rule = BUILD_MICRO_NONE;
            logger()->info('Builder will build php-cli SAPI');
        }
        try {
            // 构建对象
            $builder = BuilderProvider::makeBuilderByInput($input);
            // 根据提供的扩展列表获取依赖库列表并编译
            [$extensions, $libraries, $not_included] = DependencyUtil::getExtLibsByDeps($extensions, $libraries);

            logger()->info('Enabled extensions: ' . implode(', ', $extensions));
            logger()->info('Required libraries: ' . implode(', ', $libraries));
            if (!empty($not_included)) {
                logger()->warning('some extensions will be enabled due to dependencies: ' . implode(',', $not_included));
            }
            sleep(2);
            // 编译和检查库是否完整
            $builder->buildLibs($libraries);
            // 执行扩展检测
            $builder->proveExts($extensions);
            // 构建
            $builder->buildPHP($rule, $input->getOption('with-clean'), $input->getOption('bloat'));
            // 统计时间
            $time = round(microtime(true) - START_TIME, 3);
            logger()->info('Build complete, used ' . $time . ' s !');
            if ($rule !== BUILD_MICRO_ONLY) {
                logger()->info('Static php binary path: ' . SOURCE_PATH . '/php-src/sapi/cli/php');
            }
            if ($rule !== BUILD_MICRO_NONE) {
                logger()->info('phpmicro binary path: ' . SOURCE_PATH . '/php-src/sapi/micro/micro.sfx');
            }
            return 0;
        } catch (\Throwable $e) {
            if ($input->getOption('debug')) {
                ExceptionHandler::getInstance()->handle($e);
            } else {
                logger()->critical('Build failed with ' . get_class($e) . ': ' . $e->getMessage());
                logger()->critical('Please check with --debug option to see more details.');
            }
            return 1;
        }
    }
}
