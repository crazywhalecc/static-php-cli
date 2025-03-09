<?php

declare(strict_types=1);

namespace SPC\command;

use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\Prompt;
use Psr\Log\LogLevel;
use SPC\ConsoleApplication;
use SPC\exception\ExceptionHandler;
use SPC\exception\WrongUsageException;
use SPC\util\GlobalEnvManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use ZM\Logger\ConsoleLogger;

abstract class BaseCommand extends Command
{
    protected bool $no_motd = false;

    protected InputInterface $input;

    protected OutputInterface $output;

    public function __construct(?string $name = null)
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
                E_RECOVERABLE_ERROR => ['PHP Recoverable Error: ', 'error'],
                E_DEPRECATED => ['PHP Deprecated: ', 'notice'],
                E_USER_DEPRECATED => ['PHP User Deprecated: ', 'notice'],
            ];
            $level_tip = $tips[$error_no] ?? ['PHP Unknown: ', 'error'];
            $error = $level_tip[0] . $error_msg . ' in ' . $error_file . ' on ' . $error_line;
            logger()->{$level_tip[1]}($error);
            // 如果 return false 则错误会继续递交给 PHP 标准错误处理
            return true;
        });
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

        global $ob_logger;
        if ($input->getOption('debug')) {
            $ob_logger = new ConsoleLogger(LogLevel::DEBUG, decorated: !$input->getOption('no-ansi'));
            define('DEBUG_MODE', true);
        } else {
            $ob_logger = new ConsoleLogger(decorated: !$input->getOption('no-ansi'));
        }

        // windows fallback
        Prompt::fallbackWhen(PHP_OS_FAMILY === 'Windows');
        ConfirmPrompt::fallbackUsing(function (ConfirmPrompt $prompt) use ($input, $output) {
            $helper = new QuestionHelper();
            $case = $prompt->default ? ' [Y/n] ' : ' [y/N] ';
            $question = new ConfirmationQuestion($prompt->label . $case, $prompt->default);
            return $helper->ask($input, $output, $question);
        });

        // init GlobalEnv
        if (!$this instanceof BuildCommand) {
            GlobalEnvManager::init();
        }
        if ($this->shouldExecute()) {
            try {
                // show raw argv list for logger()->debug
                logger()->debug('argv: ' . implode(' ', $_SERVER['argv']));
                return $this->handle();
            } catch (WrongUsageException $e) {
                $msg = explode("\n", $e->getMessage());
                foreach ($msg as $v) {
                    logger()->error($v);
                }
                return static::FAILURE;
            } catch (\Throwable $e) {
                if ($this->getOption('debug')) {
                    ExceptionHandler::getInstance()->handle($e);
                } else {
                    $msg = explode("\n", $e->getMessage());
                    foreach ($msg as $v) {
                        logger()->error($v);
                    }
                }
                return static::FAILURE;
            }
        }
        return static::SUCCESS;
    }

    protected function getOption(string $name): mixed
    {
        return $this->input->getOption($name);
    }

    protected function getArgument(string $name): mixed
    {
        return $this->input->getArgument($name);
    }

    protected function shouldExecute(): bool
    {
        return true;
    }

    protected function logWithResult(bool $result, string $success_msg, string $fail_msg): int
    {
        if ($result) {
            logger()->info($success_msg);
            return static::SUCCESS;
        }
        logger()->error($fail_msg);
        return static::FAILURE;
    }

    /**
     * Parse extension list from string, replace alias and filter internal extensions.
     *
     * @param array|string $ext_list Extension string list, e.g. "mbstring,posix,sockets" or array
     */
    protected function parseExtensionList(array|string $ext_list): array
    {
        // replace alias
        $ls = array_map(function ($x) {
            $lower = strtolower(trim($x));
            if (isset(SPC_EXTENSION_ALIAS[$lower])) {
                logger()->debug("Extension [{$lower}] is an alias of [" . SPC_EXTENSION_ALIAS[$lower] . '], it will be replaced.');
                return SPC_EXTENSION_ALIAS[$lower];
            }
            return $lower;
        }, is_array($ext_list) ? $ext_list : explode(',', $ext_list));

        // filter internals
        return array_values(array_filter($ls, function ($x) {
            if (in_array($x, SPC_INTERNAL_EXTENSIONS)) {
                logger()->debug("Extension [{$x}] is an builtin extension, it will be ignored.");
                return false;
            }
            return true;
        }));
    }
}
