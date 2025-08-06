<?php

declare(strict_types=1);

namespace SPC\exception;

use SPC\util\shell\UnixShell;
use SPC\util\shell\WindowsCmd;

/**
 * Exception thrown when an error occurs during execution of shell command.
 *
 * This exception is used to indicate that a command executed by the SPC framework
 * has failed, typically due to an error in the command itself or an issue with the environment
 * in which it was executed.
 */
class ExecutionException extends SPCException
{
    public function __construct(
        private readonly string|UnixShell|WindowsCmd $cmd,
        $message = '',
        $code = 0,
        private readonly ?string $cd = null,
        private readonly array $env = [],
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the command that caused the execution error.
     *
     * @return string the command that was executed when the error occurred
     */
    public function getExecutionCommand(): string
    {
        if ($this->cmd instanceof UnixShell || $this->cmd instanceof WindowsCmd) {
            return $this->cmd->getLastCommand();
        }
        return $this->cmd;
    }

    /**
     * Returns the directory in which the command was executed.
     */
    public function getCd(): ?string
    {
        return $this->cd;
    }

    /**
     * Returns the environment variables that were set during the command execution.
     */
    public function getEnv(): array
    {
        return $this->env;
    }
}
