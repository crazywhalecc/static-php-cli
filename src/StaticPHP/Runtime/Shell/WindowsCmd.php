<?php

declare(strict_types=1);

namespace StaticPHP\Runtime\Shell;

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

        $this->passthru($cmd, $this->console_putput, $original_command, cwd: $this->cd);
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
        $result = $this->passthru($cmd, $this->console_putput, $cmd, capture_output: true, throw_on_error: false, cwd: $this->cd, env: $this->env);
        $out = explode("\n", $result['output']);
        return [$result['code'], $out];
    }

    public function getLastCommand(): string
    {
        return $this->last_cmd;
    }

    private function getExecString(string $cmd): string
    {
        return $cmd;
    }
}
