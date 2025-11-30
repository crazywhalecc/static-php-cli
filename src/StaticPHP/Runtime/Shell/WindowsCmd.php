<?php

declare(strict_types=1);

namespace StaticPHP\Runtime\Shell;

use StaticPHP\Exception\ExecutionException;
use StaticPHP\Exception\SPCInternalException;
use ZM\Logger\ConsoleColor;

class WindowsCmd extends Shell
{
    public function __construct(?bool $debug = null)
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            throw new SPCInternalException('Only windows can use WindowsCmd');
        }
        parent::__construct($debug);
    }

    public function exec(string $cmd): static
    {
        /* @phpstan-ignore-next-line */
        logger()->info(ConsoleColor::yellow('[EXEC] ') . ConsoleColor::green($cmd));

        $original_command = $cmd;
        $this->logCommandInfo($original_command);
        $this->last_cmd = $cmd = $this->getExecString($cmd);
        // echo $cmd . PHP_EOL;

        $this->passthru($cmd, $this->console_putput, $original_command, capture_output: false, throw_on_error: true);
        return $this;
    }

    public function execWithWrapper(string $wrapper, string $args): WindowsCmd
    {
        return $this->exec($wrapper . ' "' . str_replace('"', '^"', $args) . '"');
    }

    public function execWithResult(string $cmd, bool $with_log = true): array
    {
        if ($with_log) {
            /* @phpstan-ignore-next-line */
            logger()->info(ConsoleColor::blue('[EXEC] ') . ConsoleColor::green($cmd));
        } else {
            logger()->debug('Running command with result: ' . $cmd);
        }
        $cmd = $this->getExecString($cmd);
        $result = $this->passthru($cmd, $this->console_putput, $cmd, capture_output: true, throw_on_error: false);
        $out = explode("\n", $result['output']);
        return [$result['code'], $out];
    }

    public function setEnv(array $env): static
    {
        // windows currently does not support setting environment variables
        throw new SPCInternalException('Windows does not support setting environment variables in shell commands.');
    }

    public function appendEnv(array $env): static
    {
        // windows currently does not support appending environment variables
        throw new SPCInternalException('Windows does not support appending environment variables in shell commands.');
    }

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
        bool $throw_on_error = true
    ): array {
        $file_res = null;
        if ($this->enable_log_file) {
            $file_res = fopen(SPC_SHELL_LOG, 'a');
        }

        $output_value = '';
        try {
            $process = popen($cmd . ' 2>&1', 'r');
            if (!$process) {
                throw new ExecutionException(
                    cmd: $original_command ?? $cmd,
                    message: 'Failed to open process for command, popen() failed.',
                    code: -1,
                    cd: $this->cd,
                    env: $this->env
                );
            }

            while (($line = fgets($process)) !== false) {
                if (static::$passthru_callback !== null) {
                    $callback = static::$passthru_callback;
                    $callback();
                }
                if ($console_output) {
                    echo $line;
                }
                if ($file_res !== null) {
                    fwrite($file_res, $line);
                }
                if ($capture_output) {
                    $output_value .= $line;
                }
            }

            $result_code = pclose($process);

            if ($throw_on_error && $result_code !== 0) {
                if ($file_res !== null) {
                    fwrite($file_res, "Command exited with non-zero code: {$result_code}\n");
                }
                throw new ExecutionException(
                    cmd: $original_command ?? $cmd,
                    message: "Command exited with non-zero code: {$result_code}",
                    code: $result_code,
                    cd: $this->cd,
                    env: $this->env,
                );
            }

            return [
                'code' => $result_code,
                'output' => $output_value,
            ];
        } finally {
            if ($file_res !== null) {
                fclose($file_res);
            }
        }
    }

    private function getExecString(string $cmd): string
    {
        if ($this->cd !== null) {
            $cmd = 'cd /d ' . escapeshellarg($this->cd) . ' && ' . $cmd;
        }
        return $cmd;
    }
}
