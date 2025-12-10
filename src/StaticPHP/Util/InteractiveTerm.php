<?php

declare(strict_types=1);

namespace StaticPHP\Util;

use StaticPHP\DI\ApplicationContext;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZM\Logger\ConsoleColor;

class InteractiveTerm
{
    private static ?ProgressIndicator $indicator = null;

    public static function notice(string $message, bool $indent = false): void
    {
        $no_ansi = ApplicationContext::get(InputInterface::class)->getOption('no-ansi') ?? false;
        $output = ApplicationContext::get(OutputInterface::class);
        if ($output->isVerbose()) {
            logger()->notice(strip_ansi_colors($message));
        } else {
            $output->writeln(($no_ansi ? 'strip_ansi_colors' : 'strval')(ConsoleColor::cyan(($indent ? '  ' : '') . '▶ ') . $message));
        }
    }

    public static function success(string $message, bool $indent = false): void
    {
        $no_ansi = ApplicationContext::get(InputInterface::class)->getOption('no-ansi') ?? false;
        $output = ApplicationContext::get(OutputInterface::class);
        if ($output->isVerbose()) {
            logger()->info(strip_ansi_colors($message));
        } else {
            $output->writeln(($no_ansi ? 'strip_ansi_colors' : 'strval')(ConsoleColor::green(($indent ? '  ' : '') . '✔ ') . $message));
        }
    }

    public static function plain(string $message): void
    {
        $no_ansi = ApplicationContext::get(InputInterface::class)->getOption('no-ansi') ?? false;
        $output = ApplicationContext::get(OutputInterface::class);
        if ($output->isVerbose()) {
            logger()->info(strip_ansi_colors($message));
        } else {
            $output->writeln(($no_ansi ? 'strip_ansi_colors' : 'strval')($message));
        }
    }

    public static function info(string $message): void
    {
        $no_ansi = ApplicationContext::get(InputInterface::class)->getOption('no-ansi') ?? false;
        $output = ApplicationContext::get(OutputInterface::class);
        if (!$output->isVerbose()) {
            $output->writeln(($no_ansi ? 'strip_ansi_colors' : 'strval')(ConsoleColor::green('▶ ') . $message));
        }
        logger()->info(strip_ansi_colors($message));
    }

    public static function error(string $message, bool $indent = true): void
    {
        $no_ansi = ApplicationContext::get(InputInterface::class)->getOption('no-ansi') ?? false;
        $output = ApplicationContext::get(OutputInterface::class);
        if ($output->isVerbose()) {
            logger()->error(strip_ansi_colors($message));
        } else {
            $output->writeln(($no_ansi ? 'strip_ansi_colors' : 'strval')(ConsoleColor::red(($indent ? '  ' : '') . '✘ ' . $message)));
        }
    }

    public static function advance(): void
    {
        self::$indicator?->advance();
    }

    public static function setMessage(string $message): void
    {
        $no_ansi = ApplicationContext::get(InputInterface::class)->getOption('no-ansi') ?? false;
        self::$indicator?->setMessage(($no_ansi ? 'strip_ansi_colors' : 'strval')($message));
    }

    public static function finish(string $message, bool $status = true): void
    {
        $no_ansi = ApplicationContext::get(InputInterface::class)->getOption('no-ansi') ?? false;
        $message = $no_ansi ? strip_ansi_colors($message) : $message;
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
                self::$indicator->finish($message, ($no_ansi ? 'strip_ansi_colors' : 'strval')(ConsoleColor::red(' ✘')));
            } else {
                self::$indicator->finish($message, ($no_ansi ? 'strip_ansi_colors' : 'strval')(ConsoleColor::green(' ✔')));
            }
            self::$indicator = null;
        }
    }

    public static function indicateProgress(string $message): void
    {
        $no_ansi = ApplicationContext::get(InputInterface::class)->getOption('no-ansi') ?? false;
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
        // if no ansi, use a dot instead of spinner
        if ($no_ansi) {
            self::$indicator = new ProgressIndicator(ApplicationContext::get(OutputInterface::class), 'verbose', 100, [' •', ' •']);
            self::$indicator->start(strip_ansi_colors($message));
            return;
        }
        self::$indicator = new ProgressIndicator(ApplicationContext::get(OutputInterface::class), 'verbose', 100, [' ⠏', ' ⠛', ' ⠹', ' ⢸', ' ⣰', ' ⣤', ' ⣆', ' ⡇']);
        self::$indicator->start($message);
    }
}
