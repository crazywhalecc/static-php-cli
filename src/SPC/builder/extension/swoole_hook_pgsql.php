<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\util\CustomExt;

#[CustomExt('swoole-hook-pgsql')]
class swoole_hook_pgsql extends Extension
{
    public function getDistName(): string
    {
        return 'swoole';
    }

    public function validate(): void
    {
        // pdo_pgsql need to be disabled
        if ($this->builder->getExt('pdo_pgsql') !== null) {
            throw new WrongUsageException('swoole-hook-pgsql provides pdo_pgsql, if you enable pgsql hook for swoole, you must remove pdo_pgsql extension.');
        }
    }

    public function getUnixConfigureArg(): string
    {
        // enable swoole pgsql hook
        return '--enable-swoole-pgsql';
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
        if (!str_contains($out, 'coroutine_pgsql')) {
            throw new RuntimeException('swoole pgsql hook is not enabled correctly.');
        }
    }
}
