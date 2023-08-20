<?php

declare(strict_types=1);

namespace SPC;

use SPC\store\FileSystem;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;

/**
 * static-php-cli console app entry
 */
class ConsoleApplication extends Application
{
    public const VERSION = '2.0.0';

    /**
     * @throws \ReflectionException
     * @throws exception\FileSystemException
     */
    public function __construct()
    {
        parent::__construct('static-php-cli', self::VERSION);

        global $argv;

        // Detailed debugging errors are not displayed in the production environment. Only the error display provided by Symfony console is used.
        $this->setCatchExceptions(file_exists(ROOT_DIR . '/.prod') || !in_array('--debug', $argv));

        // Add subcommands by scanning the directory src/static-php-cli/command/
        $commands = FileSystem::getClassesPsr4(ROOT_DIR . '/src/SPC/command', 'SPC\\command');
        $phar = class_exists('\\Phar') && \Phar::running() || !class_exists('\\Phar');
        $commands = array_filter($commands, function ($y) use ($phar) {
            $archive_blacklist = [
                'SPC\command\dev\SortConfigCommand',
                'SPC\command\DeployCommand',
            ];
            if ($phar && in_array($y, $archive_blacklist)) {
                return false;
            }
            $reflection = new \ReflectionClass($y);
            return !$reflection->isAbstract() && !$reflection->isInterface();
        });
        $this->addCommands(array_map(function ($x) { return new $x(); }, $commands));
    }

    protected function getDefaultCommands(): array
    {
        return [new HelpCommand(), new ListCommand()];
    }
}
