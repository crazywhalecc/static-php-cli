<?php

declare(strict_types=1);

namespace SPC;

use SPC\command\DeployCommand;
use SPC\store\FileSystem;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;

/**
 * spc 应用究级入口
 */
class ConsoleApplication extends Application
{
    public const VERSION = '2.0-rc2';

    /**
     * @throws \ReflectionException
     * @throws exception\FileSystemException
     */
    public function __construct()
    {
        parent::__construct('static-php-cli', self::VERSION);

        global $argv;

        // 生产环境不显示详细的调试错误，只使用 symfony console 自带的错误显示
        $this->setCatchExceptions(file_exists(ROOT_DIR . '/.prod') || !in_array('--debug', $argv));

        // 通过扫描目录 src/static-php-cli/command/ 添加子命令
        $commands = FileSystem::getClassesPsr4(ROOT_DIR . '/src/SPC/command', 'SPC\\command');
        $this->addCommands(array_map(function ($x) { return new $x(); }, array_filter($commands, function ($y) {
            if (is_a($y, DeployCommand::class, true) && (class_exists('\\Phar') && \Phar::running() || !class_exists('\\Phar'))) {
                return false;
            }
            $reflection = new \ReflectionClass($y);
            return !$reflection->isAbstract() && !$reflection->isInterface();
        })));
    }

    /**
     * 重载以去除一些不必要的默认命令
     */
    protected function getDefaultCommands(): array
    {
        return [new HelpCommand(), new ListCommand()];
    }
}
