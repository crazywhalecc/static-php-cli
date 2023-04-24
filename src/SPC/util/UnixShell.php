<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\RuntimeException;
use ZM\Logger\ConsoleColor;

class UnixShell
{
    private ?string $cd = null;

    private bool $debug;

    public function __construct(?bool $debug = null)
    {
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
        if ($this->cd !== null) {
            $cmd = 'cd ' . escapeshellarg($this->cd) . PHP_EOL . $cmd;
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
            logger()->debug('Running command with result: ' . $cmd);
        }
        exec($cmd, $out, $code);
        return [$code, $out];
    }
}
