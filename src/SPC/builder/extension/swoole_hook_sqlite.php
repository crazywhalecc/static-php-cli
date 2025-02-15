<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\util\CustomExt;

#[CustomExt('swoole-hook-sqlite')]
class swoole_hook_sqlite extends Extension
{
    public function getDistName(): string
    {
        return 'swoole';
    }

    public function validate(): void
    {
        // pdo_pgsql need to be disabled
        if ($this->builder->getExt('pdo_sqlite') !== null) {
            throw new WrongUsageException('swoole-hook-sqlite provides pdo_sqlite, if you enable sqlite hook for swoole, you must remove pdo_sqlite extension.');
        }
    }

    public function getUnixConfigureArg(): string
    {
        // enable swoole pgsql hook
        return '--enable-swoole-sqlite';
    }

    public function runCliCheckUnix(): void
    {
        // skip if not enable swoole
        if ($this->builder->getExt('swoole') === null) {
            return;
        }
        [$ret, $out] = shell()->execWithResult(BUILD_ROOT_PATH . '/bin/php -n --ri "swoole"', false);
        $out = implode('', $out);
        if ($ret !== 0) {
            throw new RuntimeException('extension ' . $this->getName() . ' failed compile check: php-cli returned ' . $ret);
        }
        if (!str_contains($out, 'coroutine_sqlite')) {
            throw new RuntimeException('swoole sqlite hook is not enabled correctly.');
        }
    }
}
