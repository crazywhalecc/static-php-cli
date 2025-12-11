<?php

declare(strict_types=1);

namespace StaticPHP\Runtime\Shell;

use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\ExecutionException;

abstract class Shell
{
    protected ?string $cd = null;

    protected bool $console_putput;

    protected array $env = [];

    protected string $last_cmd = '';

    protected readonly bool $enable_log_file;

    protected static mixed $passthru_callback = null;

    public function __construct(?bool $console_output = null, bool $enable_log_file = true)
    {
        $this->console_putput = $console_output ?? ApplicationContext::isDebug();
        $this->enable_log_file = $enable_log_file;
    }

    public static function passthruCallback(?callable $callback): void
    {
        static::$passthru_callback = $callback;
    }

    /**
     * Equivalent to `cd` command in shell.
     *
     * @param string $dir Directory to change to
     */
    public function cd(string $dir): static
    {
        logger()->debug('Entering dir: ' . $dir);
        $c = clone $this;
        $c->cd = $dir;
        return $c;
    }

    /**
     * Set temporarily defined environment variables for current shell commands.
     *
     * @param array<string, string> $env Environment variables sets
     */
    public function setEnv(array $env): static
    {
        foreach ($env as $k => $v) {
            if (trim($v) === '') {
                continue;
            }
            $this->env[$k] = trim($v);
        }
        return $this;
    }

    /**
     * Append temporarily defined environment variables for current shell commands.
     *
     * @param array<string, string> $env Environment variables sets
     */
    public function appendEnv(array $env): static
    {
        foreach ($env as $k => $v) {
            if ($v === '') {
                continue;
            }
            if (!isset($this->env[$k])) {
                $this->env[$k] = $v;
            } else {
                $this->env[$k] = "{$v} {$this->env[$k]}";
            }
        }
        return $this;
    }

    /**
     * Executes a command in the shell.
     */
    abstract public function exec(string $cmd): static;

    /**
     * Returns the last executed command.
     */
    public function getLastCommand(): string
    {
        return $this->last_cmd;
    }

    /**
     * Returns unix-style environment variable string.
     */
    public function getEnvString(): string
    {
        $str = '';
        foreach ($this->env as $k => $v) {
            $str .= ' ' . $k . '="' . $v . '"';
        }
        return trim($str);
    }

    /**
     * Logs the command information to a log file.
     */
    protected function logCommandInfo(string $cmd): void
    {
        if (!$this->enable_log_file) {
            return;
        }
        // write executed command to log file using fwrite
        $log_file = fopen(SPC_SHELL_LOG, 'a');
        fwrite($log_file, "\n>>>>>>>>>>>>>>>>>>>>>>>>>> [" . date('Y-m-d H:i:s') . "]\n");
        fwrite($log_file, "> Executing command: {$cmd}\n");
        // get the backtrace to find the file and line number
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        if (isset($backtrace[1]['file'], $backtrace[1]['line'])) {
            $file = $backtrace[1]['file'];
            $line = $backtrace[1]['line'];
            fwrite($log_file, "> Called from: {$file} at line {$line}\n");
        }
        fwrite($log_file, "> Environment variables: {$this->getEnvString()}\n");
        if ($this->cd !== null) {
            fwrite($log_file, "> Working dir: {$this->cd}\n");
        }
        fwrite($log_file, "\n");
    }

    /**
     * Executes a command with console and log file output.
     *
     * @param string      $cmd              Full command to execute (including cd and env vars)
     * @param bool        $console_output   If true, output will be printed to console
     * @param null|string $original_command Original command string for logging
     * @param bool        $capture_output   If true, capture and return output
     * @param bool        $throw_on_error   If true, throw exception on non-zero exit code
     *
     * @return array{code: int, output: string} Returns exit code and captured output
     */
    protected function passthru(
        string $cmd,
        bool $console_output = false,
        ?string $original_command = null,
        bool $capture_output = false,
        bool $throw_on_error = true,
        ?string $cwd = null
    ): array {
        if ($cwd !== null) {
            $cwd = $cwd;
        }
        $file_res = null;
        if ($this->enable_log_file) {
            // write executed command to the log file using fwrite
            $file_res = fopen(SPC_SHELL_LOG, 'a');
        }
        if ($console_output) {
            $console_res = STDOUT;
        }
        $descriptors = [
            0 => ['file', 'php://stdin', 'r'], // stdin
            1 => PHP_OS_FAMILY === 'Windows' ? ['socket'] : ['pipe', 'w'], // stdout
            2 => PHP_OS_FAMILY === 'Windows' ? ['socket'] : ['pipe', 'w'], // stderr
        ];
        $process = proc_open($cmd, $descriptors, $pipes, $cwd);

        $output_value = '';
        try {
            if (!is_resource($process)) {
                throw new ExecutionException(
                    cmd: $original_command ?? $cmd,
                    message: 'Failed to open process for command, proc_open() failed.',
                    code: -1,
                    cd: $this->cd,
                    env: $this->env
                );
            }
            // fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            while (true) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    foreach ([$pipes[1], $pipes[2]] as $pipe) {
                        while (($chunk = fread($pipe, 8192)) !== false && $chunk !== '') {
                            if ($console_output) {
                                fwrite($console_res, $chunk);
                            }
                            if ($file_res !== null) {
                                fwrite($file_res, $chunk);
                            }
                            if ($capture_output) {
                                $output_value .= $chunk;
                            }
                        }
                    }
                    // check exit code
                    if ($throw_on_error && $status['exitcode'] !== 0) {
                        if ($file_res !== null) {
                            fwrite($file_res, "Command exited with non-zero code: {$status['exitcode']}\n");
                        }
                        throw new ExecutionException(
                            cmd: $original_command ?? $cmd,
                            message: "Command exited with non-zero code: {$status['exitcode']}",
                            code: $status['exitcode'],
                            cd: $this->cd,
                            env: $this->env,
                        );
                    }
                    break;
                }

                if (static::$passthru_callback !== null) {
                    $callback = static::$passthru_callback;
                    $callback();
                }
                $read = [$pipes[1], $pipes[2]];
                $write = null;
                $except = null;

                $ready = stream_select($read, $write, $except, 0, 100000);

                if ($ready === false) {
                    continue;
                }

                if ($ready > 0) {
                    foreach ($read as $pipe) {
                        while (($chunk = fread($pipe, 8192)) !== false && $chunk !== '') {
                            if ($console_output) {
                                fwrite($console_res, $chunk);
                            }
                            if ($file_res !== null) {
                                fwrite($file_res, $chunk);
                            }
                            if ($capture_output) {
                                $output_value .= $chunk;
                            }
                        }
                    }
                }
            }

            return [
                'code' => $status['exitcode'],
                'output' => $output_value,
            ];
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            if ($file_res !== null) {
                fclose($file_res);
            }
            proc_close($process);
        }
    }
}
