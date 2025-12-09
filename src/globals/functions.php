<?php

declare(strict_types=1);

use StaticPHP\Exception\ExecutionException;
use StaticPHP\Exception\InterruptException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Runtime\Shell\DefaultShell;
use StaticPHP\Runtime\Shell\UnixShell;
use StaticPHP\Runtime\Shell\WindowsCmd;
use ZM\Logger\ConsoleLogger;

/**
 * Judge if an array is an associative array
 */
function is_assoc_array(mixed $array): bool
{
    return is_array($array) && (!empty($array) && array_keys($array) !== range(0, count($array) - 1));
}

/**
 * Judge if an array is a list
 */
function is_list_array(mixed $array): bool
{
    return is_array($array) && (empty($array) || array_keys($array) === range(0, count($array) - 1));
}

/**
 * Return a logger instance
 */
function logger(): ConsoleLogger
{
    global $ob_logger;
    if ($ob_logger === null) {
        return new ConsoleLogger();
    }
    return $ob_logger;
}

/**
 * Transfer architecture name to gnu triplet
 */
function arch2gnu(string $arch): string
{
    $arch = strtolower($arch);
    return match ($arch) {
        'x86_64', 'x64', 'amd64' => 'x86_64',
        'arm64', 'aarch64' => 'aarch64',
        default => throw new WrongUsageException('Not support arch: ' . $arch),
        // 'armv7' => 'arm',
    };
}

/**
 * Match pattern function
 * Example: match_pattern('*.txt', 'test.txt') will return true.
 *
 * @param string $pattern Pattern string
 * @param string $subject Subject string
 */
function match_pattern(string $pattern, string $subject): bool
{
    $pattern = str_replace(['\*', '\\\.*'], ['.*', '\*'], preg_quote($pattern, '/'));
    $pattern = '/^' . $pattern . '$/i';
    return preg_match($pattern, $subject) === 1;
}

/**
 * Quote a string with a quote character
 *
 * @param string $str   String to quote
 * @param string $quote Quote character, default: `"`
 */
function quote(string $str, string $quote = '"'): string
{
    return $quote . $str . $quote;
}

function shell(?bool $debug = null): UnixShell
{
    /* @noinspection PhpUnhandledExceptionInspection */
    return new UnixShell($debug);
}

function default_shell(): DefaultShell
{
    /* @noinspection PhpUnhandledExceptionInspection */
    return new DefaultShell();
}

function cmd(?bool $debug = null): WindowsCmd
{
    /* @noinspection PhpUnhandledExceptionInspection */
    return new WindowsCmd($debug);
}

/**
 * Get current patch point.
 */
function patch_point(): string
{
    if (StaticPHP\DI\ApplicationContext::has('patch_point')) {
        /* @phpstan-ignore-next-line */
        return StaticPHP\DI\ApplicationContext::get('patch_point');
    }
    return '';
}

function patch_point_interrupt(int $retcode, string $msg = ''): InterruptException
{
    return new InterruptException(message: $msg, code: $retcode);
}

// ------- function f_* part -------
// f_ means standard function wrapper

/**
 * Execute the shell command, and the output will be directly printed in the terminal. If there is an error, an exception will be thrown
 */
function f_passthru(string $cmd): ?bool
{
    $danger = false;
    foreach (DANGER_CMD as $danger_cmd) {
        if (str_starts_with($cmd, $danger_cmd . ' ')) {
            $danger = true;
            break;
        }
    }
    if ($danger) {
        logger()->notice('Running dangerous command: ' . $cmd);
    } else {
        logger()->debug('[PASSTHRU] ' . $cmd);
    }
    $ret = passthru($cmd, $code);
    if ($code !== 0) {
        throw new ExecutionException($cmd, "Direct command run failed with code: {$code}", $code);
    }
    return $ret;
}

/**
 * Execute a command, return the output and result code
 */
function f_exec(string $command, mixed &$output, mixed &$result_code): bool|string
{
    logger()->debug('Running command (no output) : ' . $command);
    return exec($command, $output, $result_code);
}

function f_mkdir(string $directory, int $permissions = 0777, bool $recursive = false): bool
{
    if (file_exists($directory)) {
        logger()->debug("Dir {$directory} already exists, ignored");
        return true;
    }
    logger()->debug('Making new directory ' . ($recursive ? 'recursive' : '') . ': ' . $directory);
    return mkdir($directory, $permissions, $recursive);
}

function f_putenv(string $env): bool
{
    logger()->debug('Setting env: ' . $env);
    return putenv($env);
}

/**
 * Get the installed CMake version
 *
 * @return null|string The CMake version or null if it couldn't be determined
 */
function get_cmake_version(): ?string
{
    try {
        [,$output] = shell(false)->execWithResult('cmake --version', false);
        if (preg_match('/cmake version ([\d.]+)/i', $output[0], $matches)) {
            return $matches[1];
        }
    } catch (Exception $e) {
        logger()->warning('Failed to get CMake version: ' . $e->getMessage());
    }
    return null;
}

function cmake_boolean_args(string $arg_name, bool $negative = false): array
{
    $res = ["-D{$arg_name}=ON", "-D{$arg_name}=OFF"];
    return $negative ? array_reverse($res) : $res;
}

function ac_with_args(string $arg_name, bool $use_value = false): array
{
    return $use_value ? ["--with-{$arg_name}=yes", "--with-{$arg_name}=no"] : ["--with-{$arg_name}", "--without-{$arg_name}"];
}

function get_pack_replace(): array
{
    return [
        BUILD_LIB_PATH => '@build_lib_path@',
        BUILD_BIN_PATH => '@build_bin_path@',
        BUILD_INCLUDE_PATH => '@build_include_path@',
        BUILD_ROOT_PATH => '@build_root_path@',
    ];
}

/**
 * Remove duplicate spaces from a string.
 *
 * @param  string $string Input string that may contain unnecessary spaces (e.g., " -la  -lb").
 * @return string The trimmed string with only single spaces (e.g., "-la -lb").
 */
function clean_spaces(string $string): string
{
    return trim(preg_replace('/\s+/', ' ', $string));
}

/**
 * Deduplicate flags in a string. Only the last occurence of each flag will be kept.
 *                        E.g. `-lintl -lstdc++ -lphp -lstdc++` becomes `-lintl -lphp -lstdc++`
 *
 * @param  string $flags the string containing flags to deduplicate
 * @return string the deduplicated string with no duplicate flags
 */
function deduplicate_flags(string $flags): string
{
    $tokens = preg_split('/\s+/', trim($flags));

    // Reverse, unique, reverse back - keeps last occurrence of duplicates
    $deduplicated = array_reverse(array_unique(array_reverse($tokens)));

    return implode(' ', $deduplicated);
}

/**
 * Register a callback function to handle keyboard interrupts (Ctrl+C).
 *
 * @param callable $callback callback function to handle keyboard interrupts
 */
function keyboard_interrupt_register(callable $callback): void
{
    if (PHP_OS_FAMILY === 'Windows') {
        sapi_windows_set_ctrl_handler($callback);
    } elseif (extension_loaded('pcntl')) {
        global $_previous_sigint_handler;
        $_previous_sigint_handler = pcntl_signal_get_handler(SIGINT);
        pcntl_signal(SIGINT, $callback);
    }
}

/**
 * Unregister the keyboard interrupt handler.
 *
 * This function is used to remove the previously registered keyboard interrupt handler.
 * It should be called when you no longer need to handle keyboard interrupts.
 */
function keyboard_interrupt_unregister(): void
{
    if (PHP_OS_FAMILY === 'Windows') {
        sapi_windows_set_ctrl_handler(null);
    } elseif (extension_loaded('pcntl')) {
        global $_previous_sigint_handler;
        if ($_previous_sigint_handler !== null) {
            pcntl_signal(SIGINT, $_previous_sigint_handler);
            $_previous_sigint_handler = null;
            return;
        }
        pcntl_signal(SIGINT, SIG_IGN);
    }
}

/**
 * Strip ANSI color codes from a string.
 */
function strip_ansi_colors(string|Stringable $text): string
{
    // Regular expression to match ANSI escape sequences
    // Including color codes, cursor control, clear screen and other control sequences
    return preg_replace('/\e\[[0-9;]*[a-zA-Z]/', '', strval($text));
}

/**
 * Convert to a real path for display purposes, used in docker volumes.
 */
function get_display_path(string $path): string
{
    $deploy_root = getenv('SPC_FIX_DEPLOY_ROOT');
    if ($deploy_root === false) {
        return $path;
    }
    $cwd = WORKING_DIR;
    // replace build root with deploy root, only if path starts with build root
    if (str_starts_with($path, $cwd)) {
        return $deploy_root . substr($path, strlen($cwd));
    }
    throw new WrongUsageException("Cannot convert path: {$path}");
}

/**
 * Get the global DI container instance.
 *
 * @deprecated Use ApplicationContext::getContainer() or dependency injection instead.
 *             This function is kept for backward compatibility during the migration period.
 */
function spc_container(): DI\Container
{
    return \StaticPHP\DI\ApplicationContext::getContainer();
}

/**
 * Parse extension list from string, replace alias and filter internal extensions.
 *
 * @param  null|array|string $ext_list Extension list, can be array or comma-separated string
 * @return string[]          List of extension names
 */
function parse_extension_list(array|string|null $ext_list): array
{
    // standardize and trim
    $ext_list = parse_comma_list($ext_list);
    // replace alias
    $ls = array_map(function ($x) {
        $lower = strtolower(trim($x));
        if (isset(SPC_EXTENSION_ALIAS[$lower])) {
            logger()->debug("Extension [{$lower}] is an alias of [" . SPC_EXTENSION_ALIAS[$lower] . '], it will be replaced.');
            return SPC_EXTENSION_ALIAS[$lower];
        }
        return $lower;
    }, $ext_list);
    // filter internals
    return array_values(array_filter($ls, function ($x) {
        if (in_array($x, SPC_INTERNAL_EXTENSIONS)) {
            logger()->debug("Extension [{$x}] is an builtin extension, it will be ignored.");
            return false;
        }
        return true;
    }));
}

/**
 * Parse comma list from string.
 *
 * @param  null|array|string $package_list Comma list, can be array or comma-separated string
 * @return string[]          List of items
 */
function parse_comma_list(array|string|null $package_list): array
{
    if (is_string($package_list)) {
        $package_list = array_map('trim', array_filter(explode(',', $package_list)));
    }
    if (is_array($package_list)) {
        // remove duplicates
        return array_values(array_unique($package_list));
    }
    return [];
}
