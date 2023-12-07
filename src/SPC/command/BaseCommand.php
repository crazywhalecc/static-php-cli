<?php

declare(strict_types=1);

namespace SPC\command;

use Psr\Log\LogLevel;
use SPC\ConsoleApplication;
use SPC\exception\ExceptionHandler;
use SPC\exception\WrongUsageException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZM\Logger\ConsoleLogger;

abstract class BaseCommand extends Command
{
    protected bool $no_motd = false;

    protected InputInterface $input;

    protected OutputInterface $output;

    public function __construct(string $name = null)
    {
        parent::__construct($name);
        $this->addOption('debug', null, null, 'Enable debug mode');
        $this->addOption('no-motd', null, null, 'Disable motd');
    }

    public function initialize(InputInterface $input, OutputInterface $output): void
    {
        if ($input->getOption('no-motd')) {
            $this->no_motd = true;
        }

        set_error_handler(static function ($error_no, $error_msg, $error_file, $error_line) {
            $tips = [
                E_WARNING => ['PHP Warning: ', 'warning'],
                E_NOTICE => ['PHP Notice: ', 'notice'],
                E_USER_ERROR => ['PHP Error: ', 'error'],
                E_USER_WARNING => ['PHP Warning: ', 'warning'],
                E_USER_NOTICE => ['PHP Notice: ', 'notice'],
                E_STRICT => ['PHP Strict: ', 'notice'],
                E_RECOVERABLE_ERROR => ['PHP Recoverable Error: ', 'error'],
                E_DEPRECATED => ['PHP Deprecated: ', 'notice'],
                E_USER_DEPRECATED => ['PHP User Deprecated: ', 'notice'],
            ];
            $level_tip = $tips[$error_no] ?? ['PHP Unknown: ', 'error'];
            $error = $level_tip[0] . $error_msg . ' in ' . $error_file . ' on ' . $error_line;
            logger()->{$level_tip[1]}($error);
            // 如果 return false 则错误会继续递交给 PHP 标准错误处理
            return true;
        }, E_ALL | E_STRICT);
        if ($input->getOption('debug')) {
            global $ob_logger;
            $ob_logger = new ConsoleLogger(LogLevel::DEBUG);
            define('DEBUG_MODE', true);
        }
        $version = ConsoleApplication::VERSION;
        if (!$this->no_motd) {
            echo "     _        _   _                 _           
 ___| |_ __ _| |_(_) ___      _ __ | |__  _ __  
/ __| __/ _` | __| |/ __|____| '_ \\| '_ \\| '_ \\ 
\\__ \\ || (_| | |_| | (_|_____| |_) | | | | |_) |
|___/\\__\\__,_|\\__|_|\\___|    | .__/|_| |_| .__/   v{$version}
                             |_|         |_|    
";
        }
    }

    /**
     * @throws WrongUsageException
     */
    abstract public function handle(): int;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        try {
            return $this->handle();
        } catch (WrongUsageException $e) {
            foreach (explode("\n", $e->getMessage()) as $v) {
                logger()->error($v);
            }

            return static::FAILURE;
        } catch (\Throwable $e) {
            if ($this->getOption('debug')) {
                ExceptionHandler::getInstance()->handle($e);
            } else {
                foreach (explode("\n", $e->getMessage()) as $v) {
                    logger()->error($v);
                }
            }

            return static::FAILURE;
        }
    }

    protected function getOption(string $name): mixed
    {
        return $this->input->getOption($name);
    }

    protected function getArgument(string $name): mixed
    {
        return $this->input->getArgument($name);
    }
}
