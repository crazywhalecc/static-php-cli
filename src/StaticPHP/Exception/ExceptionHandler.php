<?php

declare(strict_types=1);

namespace StaticPHP\Exception;

use StaticPHP\Command\BaseCommand;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Util\InteractiveTerm;
use ZM\Logger\ConsoleColor;

/**
 * Exception handler for StaticPHP.
 * Provides centralized exception handling for the Package-based architecture.
 */
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
        RegistryException::class,
    ];

    public const array MINOR_LOG_EXCEPTIONS = [
        InterruptException::class,
        WrongUsageException::class,
        RegistryException::class,
    ];

    /** @var array<string, mixed> Build PHP extra info binding */
    private static array $build_php_extra_info = [];

    public static function handleSPCException(SPCException $e): int
    {
        // XXX error: yyy
        $head_msg = match ($class = get_class($e)) {
            BuildFailureException::class => "✘ Build failed: {$e->getMessage()}",
            DownloaderException::class => "✘ Download failed: {$e->getMessage()}",
            EnvironmentException::class => "⚠ Environment check failed: {$e->getMessage()}",
            ExecutionException::class => "✘ Command execution failed: {$e->getMessage()}",
            FileSystemException::class => "✘ File system error: {$e->getMessage()}",
            InterruptException::class => "⚠ Build interrupted by user: {$e->getMessage()}",
            PatchException::class => "✘ Patch apply failed: {$e->getMessage()}",
            SPCInternalException::class => "✘ SPC internal error: {$e->getMessage()}",
            ValidationException::class => "⚠ Validation failed: {$e->getMessage()}",
            WrongUsageException::class => $e->getMessage(),
            RegistryException::class => "✘ Registry error: {$e->getMessage()}",
            default => "✘ Unknown SPC exception {$class}: {$e->getMessage()}",
        };
        self::logError($head_msg);

        // ----------------------------------------
        $minor_logs = in_array($class, self::MINOR_LOG_EXCEPTIONS, true);

        if ($minor_logs) {
            return self::getReturnCode($e);
        }

        self::printModuleErrorInfo($e);

        // convert log file path if in docker
        $spc_log_convert = get_display_path(SPC_OUTPUT_LOG);
        $shell_log_convert = get_display_path(SPC_SHELL_LOG);
        $spc_logs_dir_convert = get_display_path(SPC_LOGS_DIR);

        self::logError('⚠ The ' . ConsoleColor::cyan('console output log') . ConsoleColor::red(' is saved in ') . ConsoleColor::cyan($spc_log_convert));
        if (file_exists(SPC_SHELL_LOG)) {
            self::logError('⚠ The ' . ConsoleColor::cyan('shell output log') . ConsoleColor::red(' is saved in ') . ConsoleColor::cyan($shell_log_convert));
        }
        if ($e->getExtraLogFiles() !== []) {
            foreach ($e->getExtraLogFiles() as $key => $file) {
                self::logError('⚠ Log file ' . ConsoleColor::cyan($key) . ' is saved in: ' . ConsoleColor::cyan("{$spc_logs_dir_convert}/{$file}"));
            }
        }
        if (!ApplicationContext::isDebug()) {
            self::logError('⚠ If you want to see more details in console, use `-vvv` option.');
        }
        return self::getReturnCode($e);
    }

    public static function handleDefaultException(\Throwable $e): int
    {
        $class = get_class($e);
        $file = $e->getFile();
        $line = $e->getLine();
        self::logError("✘ Unhandled exception {$class} on {$file} line {$line}:\n\t{$e->getMessage()}\n");
        self::logError('Stack trace:');
        self::logError(ConsoleColor::gray($e->getTraceAsString()) . PHP_EOL, 4);
        self::logError('⚠ Please report this exception to: https://github.com/crazywhalecc/static-php-cli/issues');
        return self::getReturnCode($e);
    }

    public static function bindBuildPhpExtraInfo(array $build_php_extra_info): void
    {
        self::$build_php_extra_info = $build_php_extra_info;
    }

    private static function getReturnCode(\Throwable $e): int
    {
        return match (get_class($e)) {
            BuildFailureException::class, ExecutionException::class => BaseCommand::BUILD_ERROR,
            DownloaderException::class => BaseCommand::DOWNLOAD_ERROR,
            EnvironmentException::class => BaseCommand::ENVIRONMENT_ERROR,
            FileSystemException::class => BaseCommand::FILE_SYSTEM_ERROR,
            InterruptException::class => BaseCommand::INTERRUPT_SIGNAL,
            PatchException::class => BaseCommand::PATCH_ERROR,
            ValidationException::class => BaseCommand::VALIDATION_ERROR,
            WrongUsageException::class => BaseCommand::USER_ERROR,
            default => BaseCommand::INTERNAL_ERROR,
        };
    }

    private static function logError($message, int $indent_space = 0, bool $output_log = true, string $color = 'red'): void
    {
        $spc_log = fopen(SPC_OUTPUT_LOG, 'a');
        $msg = explode("\n", (string) $message);
        foreach ($msg as $v) {
            $line = str_pad($v, strlen($v) + $indent_space, ' ', STR_PAD_LEFT);
            fwrite($spc_log, strip_ansi_colors($line) . PHP_EOL);
            if ($output_log) {
                InteractiveTerm::plain(ConsoleColor::$color($line) . '', 'error');
            }
        }
    }

    /**
     * Print array info to console and log.
     */
    private static function printArrayInfo(array $info): void
    {
        $log_output = ApplicationContext::isDebug();
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

    private static function printModuleErrorInfo(SPCException $e): void
    {
        $class = get_class($e);
        self::logError("\n-------------------- " . ConsoleColor::red('Module error info') . ' --------------------', color: 'default');

        $has_info = false;

        // Get Package information
        if ($package_info = $e->getPackageInfo()) {
            $type_label = match ($package_info['package_type']) {
                'library' => 'Library Package',
                'php-extension' => 'PHP Extension Package',
                'target' => 'Target Package',
                default => 'Package',
            };
            self::logError('Failed module: ' . ConsoleColor::gray("{$type_label} '{$package_info['package_name']}'"));
            if ($package_info['file'] && $package_info['line']) {
                self::logError('Package location: ' . ConsoleColor::gray("{$package_info['file']}:{$package_info['line']}"));
            }
            $has_info = true;
        }

        // Get Stage information (can be displayed together with Package info)
        $stage_stack = $e->getStageStack();
        if (!empty($stage_stack)) {
            // Build stage call chain: innermost -> ... -> outermost
            $stage_names = array_reverse(array_column($stage_stack, 'stage_name'));
            $stage_chain = implode(' -> ', $stage_names);

            if (count($stage_names) > 1) {
                self::logError('Failed stage: ' . ConsoleColor::gray($stage_chain));
            } else {
                self::logError('Failed stage: ' . ConsoleColor::gray($stage_names[0]));
            }

            // Show context keys of the innermost (actual failing) stage
            $innermost_stage = $stage_stack[0];
            if (!empty($innermost_stage['context_keys'])) {
                self::logError('Stage context keys: ' . ConsoleColor::gray(implode(', ', $innermost_stage['context_keys'])));
            }
            $has_info = true;
        }

        // Get PackageBuilder information
        if (!$has_info && ($builder_info = $e->getPackageBuilderInfo())) {
            self::logError('Failed module: ' . ConsoleColor::gray('PackageBuilder'));
            if ($builder_info['method']) {
                self::logError('Builder method: ' . ConsoleColor::gray($builder_info['method']));
            }
            if ($builder_info['file'] && $builder_info['line']) {
                self::logError('Builder location: ' . ConsoleColor::gray("{$builder_info['file']}:{$builder_info['line']}"));
            }
            $has_info = true;
        }

        // Get PackageInstaller information
        if (!$has_info && ($installer_info = $e->getPackageInstallerInfo())) {
            self::logError('Failed module: ' . ConsoleColor::gray('PackageInstaller'));
            if ($installer_info['method']) {
                self::logError('Installer method: ' . ConsoleColor::gray($installer_info['method']));
            }
            if ($installer_info['file'] && $installer_info['line']) {
                self::logError('Installer location: ' . ConsoleColor::gray("{$installer_info['file']}:{$installer_info['line']}"));
            }
            $has_info = true;
        }

        if (!$has_info && !in_array($class, self::KNOWN_EXCEPTIONS)) {
            self::logError('Failed From: ' . ConsoleColor::yellow('Unknown SPC module ' . $class));
        }

        // get command execution info
        if ($e instanceof ExecutionException) {
            self::logError('');
            self::logError('Failed command: ' . ConsoleColor::gray($e->getExecutionCommand()));
            if ($cd = $e->getCd()) {
                self::logError('  - Command executed in: ' . ConsoleColor::gray($cd));
            }
            if ($env = $e->getEnv()) {
                self::logError('  - Command inline env variables:');
                foreach ($env as $k => $v) {
                    self::logError(ConsoleColor::gray("{$k}={$v}"), 6);
                }
            }
        }

        // validation error
        if ($e instanceof ValidationException) {
            self::logError('Failed validation module: ' . ConsoleColor::gray($e->getValidationModuleString()));
        }

        // environment error
        if ($e instanceof EnvironmentException) {
            self::logError('Failed environment check: ' . ConsoleColor::gray($e->getMessage()));
            if (($solution = $e->getSolution()) !== null) {
                self::logError('Solution: ' . ConsoleColor::gray($solution));
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
            self::logError('', output_log: ApplicationContext::isDebug());
            self::logError('Build PHP extra info:', output_log: ApplicationContext::isDebug());
            self::printArrayInfo($info);
        }

        self::logError("---------------------------------------------------------\n", color: 'none');
    }
}
