<?php

declare(strict_types=1);

namespace SPC\util\shell;

use SPC\exception\ExecutionException;

abstract class Shell
{
    protected ?string $cd = null;

    protected bool $debug;

    protected array $env = [];

    protected string $last_cmd = '';

    protected bool $enable_log_file = true;

    public function __construct(?bool $debug = null, bool $enable_log_file = true)
    {
        $this->debug = $debug ?? defined('DEBUG_MODE');
        $this->enable_log_file = $enable_log_file;
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
     * Executes a command with console and log file output.
     *
     * @param string      $cmd              Full command to execute (including cd and env vars)
     * @param bool        $console_output   If true, output will be printed to console
     * @param null|string $original_command Original command string for logging
     */
    protected function passthru(string $cmd, bool $console_output = false, ?string $original_command = null): void
    {
        // write executed command to the log file using fwrite
        $file_res = fopen(SPC_SHELL_LOG, 'a');
        if ($console_output) {
            $console_res = STDOUT;
        }
        $descriptors = [
            0 => ['file', 'php://stdin', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];
        $process = proc_open($cmd, $descriptors, $pipes);

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
                $read = [$pipes[1], $pipes[2]];
                $write = null;
                $except = null;

                $ready = stream_select($read, $write, $except, 0, 100000);

                if ($ready === false) {
                    $status = proc_get_status($process);
                    if (!$status['running']) {
                        break;
                    }
                    continue;
                }

                if ($ready > 0) {
                    foreach ($read as $pipe) {
                        $chunk = fgets($pipe);
                        if ($chunk !== false) {
                            if ($console_output) {
                                fwrite($console_res, $chunk);
                            }
                            if ($this->enable_log_file) {
                                fwrite($file_res, $chunk);
                            }
                        }
                    }
                }

                $status = proc_get_status($process);
                if (!$status['running']) {
                    // check exit code
                    if ($status['exitcode'] !== 0) {
                        if ($this->enable_log_file) {
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
            }
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            fclose($file_res);
            proc_close($process);
        }
    }

    /**
     * Logs the command information to a log file.
     */
    abstract protected function logCommandInfo(string $cmd): void;
}
