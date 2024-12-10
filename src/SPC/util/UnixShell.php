<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\RuntimeException;
use ZM\Logger\ConsoleColor;

class UnixShell
{
    private ?string $cd = null;

    private bool $debug;

    private array $env = [];

    /**
     * @throws RuntimeException
     */
    public function __construct(?bool $debug = null)
    {
        if (PHP_OS_FAMILY === 'Windows') {
            throw new RuntimeException('Windows cannot use UnixShell');
        }
        $this->debug = $debug ?? defined('DEBUG_MODE');
    }

    public function cd(string $dir): UnixShell
    {
        logger()->info('Entering dir: ' . $dir);
        $c = clone $this;
        $c->cd = $dir;
        return $c;
    }

    /**
     * @throws RuntimeException
     */
    public function exec(string $cmd): UnixShell
    {
        /* @phpstan-ignore-next-line */
        logger()->info(ConsoleColor::yellow('[EXEC] ') . ConsoleColor::green($cmd));
        logger()->debug('Executed at: ' . debug_backtrace()[0]['file'] . ':' . debug_backtrace()[0]['line']);
        if ($this->cd !== null) {
            $cmd = 'cd ' . escapeshellarg($this->cd) . ' && ' . $cmd;
        }
        if (!$this->debug) {
            $cmd .= ' 1>/dev/null 2>&1';
        }
        f_passthru($cmd);
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
        logger()->debug('Executed at: ' . debug_backtrace()[0]['file'] . ':' . debug_backtrace()[0]['line']);
        if ($this->cd !== null) {
            $cmd = 'cd ' . escapeshellarg($this->cd) . ' && ' . $cmd;
        }
        exec($cmd, $out, $code);
        return [$code, $out];
    }

    public function setEnv(array $env): UnixShell
    {
        foreach ($env as $k => $v) {
            if ($v === '') {
                continue;
            }
            $this->env[$k] = $v;
        }
        return $this;
    }

    /**
     * @throws RuntimeException
     */
    public function execWithEnv(string $cmd): UnixShell
    {
        return $this->exec($this->getEnvString() . ' ' . $cmd);
    }

    private function getEnvString(): string
    {
        $str = '';
        foreach ($this->env as $k => $v) {
            $str .= ' ' . $k . '="' . $v . '"';
        }
        return trim($str);
    }
}
