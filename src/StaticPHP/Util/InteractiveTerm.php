<?php

declare(strict_types=1);

namespace StaticPHP\Util;

use StaticPHP\DI\ApplicationContext;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Output\OutputInterface;
use ZM\Logger\ConsoleColor;

class InteractiveTerm
{
    private static ?ProgressIndicator $indicator = null;

    public static function notice(string $message, bool $indent = false): void
    {
        $output = ApplicationContext::get(OutputInterface::class);
        if ($output->isVerbose()) {
            logger()->notice(strip_ansi_colors($message));
        } else {
            $output->writeln(ConsoleColor::cyan(($indent ? '  ' : '') . '▶ ') . $message);
        }
    }

    public static function success(string $message, bool $indent = false): void
    {
        $output = ApplicationContext::get(OutputInterface::class);
        if ($output->isVerbose()) {
            logger()->info(strip_ansi_colors($message));
        } else {
            $output->writeln(ConsoleColor::green(($indent ? '  ' : '') . '✔ ') . $message);
        }
    }

    public static function plain(string $message): void
    {
        $output = ApplicationContext::get(OutputInterface::class);
        if ($output->isVerbose()) {
            logger()->info(strip_ansi_colors($message));
        } else {
            $output->writeln($message);
        }
    }

    public static function info(string $message): void
    {
        $output = ApplicationContext::get(OutputInterface::class);
        if (!$output->isVerbose()) {
            $output->writeln(ConsoleColor::green('▶ ') . $message);
        }
        logger()->info(strip_ansi_colors($message));
    }

    public static function error(string $message, bool $indent = true): void
    {
        $output = ApplicationContext::get(OutputInterface::class);
        if ($output->isVerbose()) {
            logger()->error(strip_ansi_colors($message));
        } else {
            $output->writeln('' . ConsoleColor::red(($indent ? '  ' : '') . '✘ ' . $message));
        }
    }

    public static function advance(): void
    {
        self::$indicator?->advance();
    }

    public static function setMessage(string $message): void
    {
        self::$indicator?->setMessage($message);
    }

    public static function finish(string $message, bool $status = true): void
    {
        $output = ApplicationContext::get(OutputInterface::class);
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
                self::$indicator->finish($message, '' . ConsoleColor::red(' ✘'));
            } else {
                self::$indicator->finish($message, '' . ConsoleColor::green(' ✔'));
            }
            self::$indicator = null;
        }
    }

    public static function indicateProgress(string $message): void
    {
        $output = ApplicationContext::get(OutputInterface::class);
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
        self::$indicator = new ProgressIndicator(ApplicationContext::get(OutputInterface::class), 'verbose', 100, [' ⠏', ' ⠛', ' ⠹', ' ⢸', ' ⣰', ' ⣤', ' ⣆', ' ⡇']);
        self::$indicator->start($message);
    }
}
