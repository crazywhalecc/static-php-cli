<?php

declare(strict_types=1);

namespace StaticPHP\Util;

use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use ZM\Logger\ConsoleColor;

class InteractiveTerm
{
    private static ?ProgressIndicator $indicator = null;

    private static ?OutputInterface $output = null;

    private static ?bool $noAnsi = null;

    /**
     * Initialize with a real Symfony Console input/output (called from ConsoleApplication::doRun()).
     * After this call, all output goes through the configured Console, and noAnsi reflects
     * the user's --no-ansi flag.
     */
    public static function init(InputInterface $input, OutputInterface $output): void
    {
        self::$output = $output;
        try {
            self::$noAnsi = (bool) $input->getOption('no-ansi');
        } catch (\InvalidArgumentException) {
            // Symfony hasn't bound the application-level input definition yet
            // (e.g. ArgvInput passed directly to doRun() on some versions).
            self::$noAnsi = false;
        }
    }

    public static function notice(string $message, bool $indent = false): void
    {
        $no_ansi = self::noAnsi();
        $output = self::output();
        if ($output->isVerbose()) {
            logger()->notice(strip_ansi_colors($message));
        } else {
            $output->writeln(($no_ansi ? 'strip_ansi_colors' : 'strval')(ConsoleColor::cyan(($indent ? '  ' : '') . '▶ ') . $message));
            logger()->debug(strip_ansi_colors($message));
        }
    }

    public static function success(string $message, bool $indent = false): void
    {
        $no_ansi = self::noAnsi();
        $output = self::output();
        if ($output->isVerbose()) {
            logger()->info(strip_ansi_colors($message));
        } else {
            $output->writeln(($no_ansi ? 'strip_ansi_colors' : 'strval')(ConsoleColor::green(($indent ? '  ' : '') . '✔ ') . $message));
            logger()->debug(strip_ansi_colors($message));
        }
    }

    public static function plain(string $message, string $level = 'info'): void
    {
        $no_ansi = self::noAnsi();
        $output = self::output();
        if ($output->isVerbose()) {
            match ($level) {
                'debug' => logger()->debug(strip_ansi_colors($message)),
                'notice' => logger()->notice(strip_ansi_colors($message)),
                'warning' => logger()->warning(strip_ansi_colors($message)),
                'error' => logger()->error(strip_ansi_colors($message)),
                default => logger()->info(strip_ansi_colors($message)),
            };
        } else {
            $output = $level === 'error' && $output instanceof ConsoleOutput ? $output->getErrorOutput() : $output;
            $output->writeln(($no_ansi ? 'strip_ansi_colors' : 'strval')($message));
        }
    }

    public static function info(string $message): void
    {
        $no_ansi = self::noAnsi();
        $output = self::output();
        if (!$output->isVerbose()) {
            $output->writeln(($no_ansi ? 'strip_ansi_colors' : 'strval')(ConsoleColor::green('▶ ') . $message));
        }
        logger()->info(strip_ansi_colors($message));
    }

    public static function error(string $message, bool $indent = true): void
    {
        $no_ansi = self::noAnsi();
        $output = self::output();
        if ($output->isVerbose()) {
            logger()->error(strip_ansi_colors($message));
        } else {
            $output->writeln(($no_ansi ? 'strip_ansi_colors' : 'strval')(ConsoleColor::red(($indent ? '  ' : '') . '✘ ' . $message)));
            logger()->debug(strip_ansi_colors($message));
        }
    }

    public static function advance(): void
    {
        self::$indicator?->advance();
    }

    public static function setMessage(string $message): void
    {
        $no_ansi = self::noAnsi();
        self::$indicator?->setMessage(($no_ansi ? 'strip_ansi_colors' : 'strval')($message));
        logger()->debug(strip_ansi_colors($message));
    }

    public static function finish(string $message, bool $status = true): void
    {
        $no_ansi = self::noAnsi();
        $message = $no_ansi ? strip_ansi_colors($message) : $message;
        $output = self::output();
        if ($output->isVerbose()) {
            if ($status) {
                logger()->info($message);
            } else {
                logger()->error($message);
            }
            return;
        }
        if (self::$indicator !== null) {
            if (!$status) {
                self::$indicator->finish($message, ($no_ansi ? 'strip_ansi_colors' : 'strval')(ConsoleColor::red(' ✘')));
            } else {
                self::$indicator->finish($message, ($no_ansi ? 'strip_ansi_colors' : 'strval')(ConsoleColor::green(' ✔')));
            }
            self::$indicator = null;
        }
    }

    public static function indicateProgress(string $message): void
    {
        $no_ansi = self::noAnsi();
        $output = self::output();
        if ($output->isVerbose()) {
            logger()->info(strip_ansi_colors($message));
            return;
        }
        if (self::$indicator !== null) {
            // just reuse existing indicator, change
            self::setMessage($message);
            self::$indicator->advance();
            return;
        }
        logger()->debug(strip_ansi_colors($message));
        // if no ansi, use a dot instead of spinner
        if ($no_ansi) {
            self::$indicator = new ProgressIndicator(self::output(), 'verbose', 100, [' •', ' •']);
            self::$indicator->start(strip_ansi_colors($message));
            return;
        }
        self::$indicator = new ProgressIndicator(self::output(), 'verbose', 100, [' ⠏', ' ⠛', ' ⠹', ' ⢸', ' ⣰', ' ⣤', ' ⣆', ' ⡇']);
        self::$indicator->start($message);
    }

    /**
     * Lazy default initialization used when init() was never called (early-boot errors,
     * tests, programmatic usage). Creates a plain STDERR output so error messages are
     * visible without depending on the Symfony Console lifecycle.
     */
    private static function initDefault(): void
    {
        if (self::$output !== null) {
            return;
        }
        self::$output = new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, false);
        self::$noAnsi = false;
    }

    private static function noAnsi(): bool
    {
        if (self::$output === null) {
            self::initDefault();
        }
        return self::$noAnsi ?? false;
    }

    private static function output(): OutputInterface
    {
        if (self::$output === null) {
            self::initDefault();
        }
        return self::$output;
    }
}
