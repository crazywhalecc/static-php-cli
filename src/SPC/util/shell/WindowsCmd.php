<?php

declare(strict_types=1);

namespace SPC\util\shell;

use SPC\exception\SPCInternalException;
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

        $this->passthru($cmd, $this->debug, $original_command);
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
        exec($cmd, $out, $code);
        return [$code, $out];
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

    protected function logCommandInfo(string $cmd): void
    {
        // write executed command to the log file using fwrite
        $log_file = fopen(SPC_SHELL_LOG, 'a');
        fwrite($log_file, "\n>>>>>>>>>>>>>>>>>>>>>>>>>> [" . date('Y-m-d H:i:s') . "]\n");
        fwrite($log_file, "> Executing command: {$cmd}\n");
        if ($this->cd !== null) {
            fwrite($log_file, "> Working dir: {$this->cd}\n");
        }
        fwrite($log_file, "\n");
    }

    private function getExecString(string $cmd): string
    {
        if ($this->cd !== null) {
            $cmd = 'cd /d ' . escapeshellarg($this->cd) . ' && ' . $cmd;
        }
        return $cmd;
    }
}
