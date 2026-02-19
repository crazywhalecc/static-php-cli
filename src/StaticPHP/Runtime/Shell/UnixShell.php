<?php

declare(strict_types=1);

namespace StaticPHP\Runtime\Shell;

use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\SystemTarget;
use ZM\Logger\ConsoleColor;

/**
 * Unix-like OS shell command executor.
 *
 * This class provides methods to execute shell commands in a Unix-like environment.
 * It supports setting environment variables and changing the working directory.
 */
class UnixShell extends Shell
{
    public function __construct(?bool $console_output = null)
    {
        if (PHP_OS_FAMILY === 'Windows') {
            throw new SPCInternalException('Windows cannot use UnixShell');
        }
        parent::__construct($console_output);
    }

    public function exec(string $cmd): static
    {
        $cmd = clean_spaces($cmd);
        /* @phpstan-ignore-next-line */
        logger()->info(ConsoleColor::yellow('[EXEC] ') . ConsoleColor::green($cmd));
        $original_command = $cmd;
        $this->logCommandInfo($original_command);
        $this->last_cmd = $cmd = $this->getExecString($cmd);
        $this->passthru($cmd, $this->console_putput, $original_command, cwd: $this->cd);
        return $this;
    }

    /**
     * Init the environment variable that common build will be used.
     *
     * @param LibraryPackage $library Library package
     */
    public function initializeEnv(LibraryPackage $library): UnixShell
    {
        $this->setEnv([
            'CFLAGS' => $library->getLibExtraCFlags(),
            'CXXFLAGS' => $library->getLibExtraCXXFlags(),
            'LDFLAGS' => $library->getLibExtraLdFlags(),
            'LIBS' => $library->getLibExtraLibs() . SystemTarget::getRuntimeLibs(),
        ]);
        return $this;
    }

    /**
     * Execute a command and return the result.
     *
     * @param  string                     $cmd      Command to execute
     * @param  bool                       $with_log Whether to log the command
     * @return array{0: int, 1: string[]} Return code and output lines
     */
    public function execWithResult(string $cmd, bool $with_log = true): array
    {
        if ($with_log) {
            /* @phpstan-ignore-next-line */
            logger()->info(ConsoleColor::blue('[EXEC] ') . ConsoleColor::green($cmd));
        } else {
            /* @phpstan-ignore-next-line */
            logger()->debug(ConsoleColor::blue('[EXEC] ') . ConsoleColor::gray($cmd));
        }
        $cmd = $this->getExecString($cmd);
        $this->logCommandInfo($cmd);
        $result = $this->passthru($cmd, $this->console_putput, $cmd, capture_output: true, throw_on_error: false, cwd: $this->cd);
        $out = explode("\n", $result['output']);
        return [$result['code'], $out];
    }

    private function getExecString(string $cmd): string
    {
        // logger()->debug('Executed at: ' . debug_backtrace()[0]['file'] . ':' . debug_backtrace()[0]['line']);
        $env_str = $this->getEnvString();
        if (!empty($env_str)) {
            $cmd = "{$env_str} {$cmd}";
        }
        return $cmd;
    }
}
