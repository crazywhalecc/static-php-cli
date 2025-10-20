<?php

declare(strict_types=1);

namespace SPC\util\shell;

use SPC\builder\freebsd\library\BSDLibraryBase;
use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\exception\SPCInternalException;
use SPC\util\SPCTarget;
use ZM\Logger\ConsoleColor;

/**
 * Unix-like OS shell command executor.
 *
 * This class provides methods to execute shell commands in a Unix-like environment.
 * It supports setting environment variables and changing the working directory.
 */
class UnixShell extends Shell
{
    public function __construct(?bool $debug = null)
    {
        if (PHP_OS_FAMILY === 'Windows') {
            throw new SPCInternalException('Windows cannot use UnixShell');
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
        $this->passthru($cmd, $this->debug, $original_command);
        return $this;
    }

    /**
     * Init the environment variable that common build will be used.
     *
     * @param BSDLibraryBase|LinuxLibraryBase|MacOSLibraryBase $library Library class
     */
    public function initializeEnv(BSDLibraryBase|LinuxLibraryBase|MacOSLibraryBase $library): UnixShell
    {
        $this->setEnv([
            'CFLAGS' => $library->getLibExtraCFlags(),
            'CXXFLAGS' => $library->getLibExtraCXXFlags(),
            'LDFLAGS' => $library->getLibExtraLdFlags(),
            'LIBS' => $library->getLibExtraLibs() . SPCTarget::getRuntimeLibs(),
        ]);
        return $this;
    }

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
        exec($cmd, $out, $code);
        return [$code, $out];
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

    protected function logCommandInfo(string $cmd): void
    {
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

    private function getExecString(string $cmd): string
    {
        // logger()->debug('Executed at: ' . debug_backtrace()[0]['file'] . ':' . debug_backtrace()[0]['line']);
        $env_str = $this->getEnvString();
        if (!empty($env_str)) {
            $cmd = "{$env_str} {$cmd}";
        }
        if ($this->cd !== null) {
            $cmd = 'cd ' . escapeshellarg($this->cd) . ' && ' . $cmd;
        }
        return $cmd;
    }
}
