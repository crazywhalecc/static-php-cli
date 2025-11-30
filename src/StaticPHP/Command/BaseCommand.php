<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\ExceptionHandler;
use StaticPHP\Exception\SPCException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    /**
     * The message of the day (MOTD) displayed when the command is run.
     * You can customize this to show your application's name and version if you are using SPC in vendor mode.
     */
    public static string $motd = ' ____  _        _   _      ____  _   _ ____
/ ___|| |_ __ _| |_(_) ___|  _ \| | | |  _ \
\___ \| __/ _` | __| |/ __| |_) | |_| | |_) |
 ___) | || (_| | |_| | (__|  __/|  _  |  __/
|____/ \__\__,_|\__|_|\___|_|   |_| |_|_|    v{version}

';

    protected bool $no_motd = false;

    protected InputInterface $input;

    protected OutputInterface $output;

    public function __construct(?string $name = null)
    {
        parent::__construct($name);
        $this->addOption('debug', null, null, '(deprecated) Enable debug mode');
        $this->addOption('no-motd', null, null, 'Disable motd');
    }

    public function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;
        $this->output = $output;

        // Bind command context to ApplicationContext
        ApplicationContext::bindCommandContext($input, $output);

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
        $version = $this->getVersionWithCommit();
        if (!$this->no_motd) {
            echo str_replace('{version}', $version, self::$motd);
        }
    }

    abstract public function handle(): int;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // handle verbose option
            $level = match ($this->output->getVerbosity()) {
                OutputInterface::VERBOSITY_VERBOSE => 'info',
                OutputInterface::VERBOSITY_VERY_VERBOSE, OutputInterface::VERBOSITY_DEBUG => 'debug',
                default => 'warning',
            };
            logger()->setLevel($level);

            // ansi
            if ($this->input->getOption('no-ansi')) {
                logger()->setDecorated(false);
            }

            // Set debug mode in ApplicationContext
            $isDebug = $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE;
            ApplicationContext::setDebug($isDebug);

            // show raw argv list for logger()->debug
            logger()->debug('argv: ' . implode(' ', $_SERVER['argv']));
            return $this->handle();
        } /* @noinspection PhpRedundantCatchClauseInspection */ catch (SPCException $e) {
            // Handle SPCException and log it
            ExceptionHandler::handleSPCException($e);
            return static::FAILURE;
        } catch (\Throwable $e) {
            // Handle any other exceptions
            ExceptionHandler::handleDefaultException($e);
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

    /**
     * Get version string with git commit short ID if available.
     */
    private function getVersionWithCommit(): string
    {
        $version = $this->getApplication()->getVersion();

        // Don't show commit ID when running in phar
        if (\Phar::running()) {
            return $version;
        }

        $commitId = $this->getGitCommitShortId();
        if ($commitId) {
            return "{$version} ({$commitId})";
        }

        return $version;
    }

    /**
     * Get git commit short ID without executing git command.
     */
    private function getGitCommitShortId(): ?string
    {
        try {
            $gitDir = ROOT_DIR . '/.git';

            if (!is_dir($gitDir)) {
                return null;
            }

            $headFile = $gitDir . '/HEAD';
            if (!file_exists($headFile)) {
                return null;
            }

            $head = trim(file_get_contents($headFile));

            // If HEAD contains 'ref:', it's a branch reference
            if (str_starts_with($head, 'ref: ')) {
                $ref = substr($head, 5);
                $refFile = $gitDir . '/' . $ref;

                if (file_exists($refFile)) {
                    $commit = trim(file_get_contents($refFile));
                    return substr($commit, 0, 7);
                }
            } else {
                // HEAD contains the commit hash directly (detached HEAD)
                return substr($head, 0, 7);
            }
        } catch (\Throwable) {
            // Silently fail if we can't read git info
        }

        return null;
    }
}
