<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\RuntimeException;
use ZM\Logger\ConsoleColor;

class WindowsCmd
{
    private ?string $cd = null;

    private bool $debug;

    private array $env = [];

    /**
     * @throws RuntimeException
     */
    public function __construct(?bool $debug = null)
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            throw new RuntimeException('Only windows can use WindowsCmd');
        }
        $this->debug = $debug ?? defined('DEBUG_MODE');
    }

    public function cd(string $dir): WindowsCmd
    {
        logger()->info('Entering dir: ' . $dir);
        $c = clone $this;
        $c->cd = $dir;
        return $c;
    }

    /**
     * @throws RuntimeException
     */
    public function exec(string $cmd): WindowsCmd
    {
        /* @phpstan-ignore-next-line */
        logger()->info(ConsoleColor::yellow('[EXEC] ') . ConsoleColor::green($cmd));
        if ($this->cd !== null) {
            $cmd = 'cd /d ' . escapeshellarg($this->cd) . ' && ' . $cmd;
        }
        if (!$this->debug) {
            $cmd .= ' >nul 2>&1';
        }
        echo $cmd . PHP_EOL;

        f_passthru($cmd);
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

    public function setEnv(array $env): WindowsCmd
    {
        $this->env = array_merge($this->env, $env);
        return $this;
    }

    /**
     * @throws RuntimeException
     */
    public function execWithEnv(string $cmd): WindowsCmd
    {
        if ($this->getEnvString() !== '') {
            return $this->exec($this->getEnvString() . "call {$cmd}");
        }
        return $this->exec($cmd);
    }

    private function getEnvString(): string
    {
        $str = '';
        foreach ($this->env as $k => $v) {
            $str .= 'set ' . $k . '=' . $v . ' && ';
        }
        return $str;
    }
}
