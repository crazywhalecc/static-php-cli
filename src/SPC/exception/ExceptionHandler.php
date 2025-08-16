<?php

declare(strict_types=1);

namespace SPC\exception;

use SPC\builder\BuilderBase;
use SPC\builder\freebsd\BSDBuilder;
use SPC\builder\linux\LinuxBuilder;
use SPC\builder\macos\MacOSBuilder;
use SPC\builder\windows\WindowsBuilder;
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

    /** @var null|BuilderBase Builder binding */
    private static ?BuilderBase $builder = null;

    /** @var array<string, mixed> Build PHP extra info binding */
    private static array $build_php_extra_info = [];

    public static function handleSPCException(SPCException $e): void
    {
        // XXX error: yyy
        $head_msg = match ($class = get_class($e)) {
            BuildFailureException::class => "✗ Build failed: {$e->getMessage()}",
            DownloaderException::class => "✗ Download failed: {$e->getMessage()}",
            EnvironmentException::class => "⚠ Environment check failed: {$e->getMessage()}",
            ExecutionException::class => "✗ Command execution failed: {$e->getMessage()}",
            FileSystemException::class => "✗ File system error: {$e->getMessage()}",
            InterruptException::class => "⚠ Build interrupted by user: {$e->getMessage()}",
            PatchException::class => "✗ Patch apply failed: {$e->getMessage()}",
            SPCInternalException::class => "✗ SPC internal error: {$e->getMessage()}",
            ValidationException::class => "⚠ Validation failed: {$e->getMessage()}",
            WrongUsageException::class => $e->getMessage(),
            default => "✗ Unknown SPC exception {$class}: {$e->getMessage()}",
        };
        self::logError($head_msg);

        // ----------------------------------------
        $minor_logs = in_array($class, self::MINOR_LOG_EXCEPTIONS, true);

        if ($minor_logs) {
            return;
        }

        self::logError("----------------------------------------\n");

        // get the SPCException module
        if ($lib_info = $e->getLibraryInfo()) {
            self::logError('Failed module: ' . ConsoleColor::yellow("library {$lib_info['library_name']} builder for {$lib_info['os']}"));
        } elseif ($ext_info = $e->getExtensionInfo()) {
            self::logError('Failed module: ' . ConsoleColor::yellow("shared extension {$ext_info['extension_name']} builder"));
        } elseif (self::$builder) {
            $os = match (get_class(self::$builder)) {
                WindowsBuilder::class => 'Windows',
                MacOSBuilder::class => 'macOS',
                LinuxBuilder::class => 'Linux',
                BSDBuilder::class => 'FreeBSD',
                default => 'Unknown OS',
            };
            self::logError('Failed module: ' . ConsoleColor::yellow("Builder for {$os}"));
        } elseif (!in_array($class, self::KNOWN_EXCEPTIONS)) {
            self::logError('Failed From: ' . ConsoleColor::yellow('Unknown SPC module ' . $class));
        }

        // get command execution info
        if ($e instanceof ExecutionException) {
            self::logError('');
            self::logError('Failed command: ' . ConsoleColor::yellow($e->getExecutionCommand()));
            if ($cd = $e->getCd()) {
                self::logError('Command executed in: ' . ConsoleColor::yellow($cd));
            }
            if ($env = $e->getEnv()) {
                self::logError('Command inline env variables:');
                foreach ($env as $k => $v) {
                    self::logError(ConsoleColor::yellow("{$k}={$v}"), 4);
                }
            }
        }

        // validation error
        if ($e instanceof ValidationException) {
            self::logError('Failed validation module: ' . ConsoleColor::yellow($e->getValidationModuleString()));
        }

        // environment error
        if ($e instanceof EnvironmentException) {
            self::logError('Failed environment check: ' . ConsoleColor::yellow($e->getMessage()));
            if (($solution = $e->getSolution()) !== null) {
                self::logError('Solution: ' . ConsoleColor::yellow($solution));
            }
        }

        // get patch info
        if ($e instanceof PatchException) {
            self::logError("Failed patch module: {$e->getPatchModule()}");
        }

        // get internal trace
        if ($e instanceof SPCInternalException) {
            self::logError('Internal trace:');
            self::logError(ConsoleColor::gray("{$e->getTraceAsString()}\n"), 4);
        }

        // get the full build info if possible
        if ($info = ExceptionHandler::$build_php_extra_info) {
            self::logError('', output_log: defined('DEBUG_MODE'));
            self::logError('Build PHP extra info:', output_log: defined('DEBUG_MODE'));
            self::printArrayInfo($info);
        }

        // get the full builder options if possible
        if ($e->getBuildPHPInfo()) {
            $info = $e->getBuildPHPInfo();
            self::logError('', output_log: defined('DEBUG_MODE'));
            self::logError('Builder function: ' . ConsoleColor::yellow($info['builder_function']), output_log: defined('DEBUG_MODE'));
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
        self::logError("✗ Unhandled exception {$class}:\n\t{$e->getMessage()}\n");
        self::logError('Stack trace:');
        self::logError(ConsoleColor::gray($e->getTraceAsString()) . PHP_EOL, 4);
        self::logError('⚠ Please report this exception to: https://github.com/crazywhalecc/static-php-cli/issues');
    }

    public static function bindBuilder(?BuilderBase $bind_builder): void
    {
        self::$builder = $bind_builder;
    }

    public static function bindBuildPhpExtraInfo(array $build_php_extra_info): void
    {
        self::$build_php_extra_info = $build_php_extra_info;
    }

    private static function logError($message, int $indent_space = 0, bool $output_log = true): void
    {
        $spc_log = fopen(SPC_OUTPUT_LOG, 'a');
        $msg = explode("\n", (string) $message);
        foreach ($msg as $v) {
            $line = str_pad($v, strlen($v) + $indent_space, ' ', STR_PAD_LEFT);
            fwrite($spc_log, strip_ansi_colors($line) . PHP_EOL);
            if ($output_log) {
                echo ConsoleColor::red($line) . PHP_EOL;
            }
        }
    }

    /**
     * Print array info to console and log.
     */
    private static function printArrayInfo(array $info): void
    {
        $log_output = defined('DEBUG_MODE');
        $maxlen = 0;
        foreach ($info as $k => $v) {
            $maxlen = max(strlen($k), $maxlen);
        }
        foreach ($info as $k => $v) {
            if (is_string($v)) {
                if ($v === '') {
                    self::logError($k . ': ' . str_pad('', $maxlen - strlen($k)) . ConsoleColor::yellow('""'), 4, $log_output);
                } else {
                    self::logError($k . ': ' . str_pad('', $maxlen - strlen($k)) . ConsoleColor::yellow($v), 4, $log_output);
                }
            } elseif (is_array($v) && !is_assoc_array($v)) {
                if ($v === []) {
                    self::logError($k . ': ' . str_pad('', $maxlen - strlen($k)) . ConsoleColor::yellow('[]'), 4, $log_output);
                    continue;
                }
                $first = array_shift($v);
                self::logError($k . ': ' . str_pad('', $maxlen - strlen($k)) . ConsoleColor::yellow($first), 4, $log_output);
                foreach ($v as $vs) {
                    self::logError(str_pad('', $maxlen + 2) . ConsoleColor::yellow($vs), 4, $log_output);
                }
            } elseif (is_bool($v) || is_null($v)) {
                self::logError($k . ': ' . str_pad('', $maxlen - strlen($k)) . ConsoleColor::cyan($v === true ? 'true' : ($v === false ? 'false' : 'null')), 4, $log_output);
            } else {
                self::logError($k . ': ' . str_pad('', $maxlen - strlen($k)) . ConsoleColor::yellow(json_encode($v, JSON_PRETTY_PRINT)), 4, $log_output);
            }
        }
    }
}
