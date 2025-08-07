<?php

declare(strict_types=1);

namespace SPC\exception;

use ZM\Logger\ConsoleColor;

class ExceptionHandler
{
    public const array KNOWN_EXCEPTIONS = [
        BuildFailureException::class,
        DownloaderException::class,
        EnvironmentException::class,
        ExecutionException::class,
        FileSystemException::class,
        InterruptException::class,
        PatchException::class,
        SPCInternalException::class,
        ValidationException::class,
        WrongUsageException::class,
    ];

    public const array MINOR_LOG_EXCEPTIONS = [
        InterruptException::class,
        WrongUsageException::class,
    ];

    public static function handleSPCException(SPCException $e): void
    {
        // XXX error: yyy
        $head_msg = match ($class = get_class($e)) {
            BuildFailureException::class => "Build failed: {$e->getMessage()}",
            DownloaderException::class => "Download failed: {$e->getMessage()}",
            EnvironmentException::class => "Environment check failed: {$e->getMessage()}",
            ExecutionException::class => "Command execution failed: {$e->getMessage()}",
            FileSystemException::class => "File system error: {$e->getMessage()}",
            InterruptException::class => "⚠ Build interrupted by user: {$e->getMessage()}",
            PatchException::class => "Patch apply failed: {$e->getMessage()}",
            SPCInternalException::class => "SPC internal error: {$e->getMessage()}",
            ValidationException::class => "Validation failed: {$e->getMessage()}",
            WrongUsageException::class => $e->getMessage(),
            default => "Unknown SPC exception {$class}: {$e->getMessage()}",
        };
        self::logError($head_msg);

        // ----------------------------------------
        $minor_logs = in_array($class, self::MINOR_LOG_EXCEPTIONS, true);

        if ($minor_logs) {
            return;
        }

        self::logError("----------------------------------------\n");

        // get the SPCException module
        if ($php_info = $e->getBuildPHPInfo()) {
            self::logError('✗ Failed module: ' . ConsoleColor::yellow("PHP builder {$php_info['builder_class']} for {$php_info['os']}"));
        } elseif ($lib_info = $e->getLibraryInfo()) {
            self::logError('✗ Failed module: ' . ConsoleColor::yellow("library {$lib_info['library_name']} builder for {$lib_info['os']}"));
        } elseif ($ext_info = $e->getExtensionInfo()) {
            self::logError('✗ Failed module: ' . ConsoleColor::yellow("shared extension {$ext_info['extension_name']} builder"));
        } elseif (!in_array($class, self::KNOWN_EXCEPTIONS)) {
            self::logError('✗ Failed From: ' . ConsoleColor::yellow('Unknown SPC module ' . $class));
        }
        self::logError('');

        // get command execution info
        if ($e instanceof ExecutionException) {
            self::logError('✗ Failed command: ' . ConsoleColor::yellow($e->getExecutionCommand()));
            if ($cd = $e->getCd()) {
                self::logError('✗ Command executed in: ' . ConsoleColor::yellow($cd));
            }
            if ($env = $e->getEnv()) {
                self::logError('✗ Command inline env variables:');
                foreach ($env as $k => $v) {
                    self::logError(ConsoleColor::yellow("{$k}={$v}"), 4);
                }
            }
        }

        // validation error
        if ($e instanceof ValidationException) {
            self::logError('✗ Failed validation module: ' . ConsoleColor::yellow($e->getValidationModuleString()));
        }

        // environment error
        if ($e instanceof EnvironmentException) {
            self::logError('✗ Failed environment check: ' . ConsoleColor::yellow($e->getMessage()));
            if (($solution = $e->getSolution()) !== null) {
                self::logError('✗ Solution: ' . ConsoleColor::yellow($solution));
            }
        }

        // get patch info
        if ($e instanceof PatchException) {
            self::logError("✗ Failed patch module: {$e->getPatchModule()}");
        }

        // get internal trace
        if ($e instanceof SPCInternalException) {
            self::logError('✗ Internal trace:');
            self::logError(ConsoleColor::gray("{$e->getTraceAsString()}\n"), 4);
        }

        // get the full build info if possible
        if (($info = $e->getBuildPHPExtraInfo()) && defined('DEBUG_MODE')) {
            self::logError('✗ Build PHP extra info:');
            $maxlen = 0;
            foreach ($info as $k => $v) {
                $maxlen = max(strlen($k), $maxlen);
            }
            foreach ($info as $k => $v) {
                if (is_string($v)) {
                    self::logError($k . ': ' . str_pad('', $maxlen - strlen($k)) . ConsoleColor::yellow($v), 4);
                } elseif (is_array($v) && !is_assoc_array($v)) {
                    $first = array_shift($v);
                    self::logError($k . ': ' . str_pad('', $maxlen - strlen($k)) . ConsoleColor::yellow($first), 4);
                    foreach ($v as $vs) {
                        self::logError(str_pad('', $maxlen + 2) . ConsoleColor::yellow($vs), 4);
                    }
                }
            }
        }

        self::logError("\n----------------------------------------\n");

        self::logError('⚠ The ' . ConsoleColor::cyan('console output log') . ConsoleColor::red(' is saved in ') . ConsoleColor::none(SPC_OUTPUT_LOG));
        if (file_exists(SPC_SHELL_LOG)) {
            self::logError('⚠ The ' . ConsoleColor::cyan('shell output log') . ConsoleColor::red(' is saved in ') . ConsoleColor::none(SPC_SHELL_LOG));
        }
        if ($e->getExtraLogFiles() !== []) {
            foreach ($e->getExtraLogFiles() as $key => $file) {
                self::logError("⚠ Log file [{$key}] is saved in: " . ConsoleColor::none(SPC_LOGS_DIR . "/{$file}"));
            }
        }
        if (!defined('DEBUG_MODE')) {
            self::logError('⚠ If you want to see more details in console, use `--debug` option.');
        }
    }

    public static function handleDefaultException(\Throwable $e): void
    {
        $class = get_class($e);
        self::logError("Unhandled exception {$class}: {$e->getMessage()}\n\t{$e->getMessage()}\n");
        self::logError('Stack trace:');
        self::logError(ConsoleColor::gray($e->getTraceAsString()), 4);
        self::logError('Please report this exception to: https://github.com/crazywhalecc/static-php-cli/issues');
    }

    private static function logError($message, int $indent_space = 0): void
    {
        $spc_log = fopen(SPC_OUTPUT_LOG, 'a');
        $msg = explode("\n", (string) $message);
        foreach ($msg as $v) {
            $line = str_pad($v, strlen($v) + $indent_space, ' ', STR_PAD_LEFT);
            fwrite($spc_log, strip_ansi_colors($line) . PHP_EOL);
            echo ConsoleColor::red($line) . PHP_EOL;
        }
    }
}
