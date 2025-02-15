<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\RuntimeException;
use SPC\util\CustomExt;

#[CustomExt('swoole-hook-mysql')]
class swoole_hook_mysql extends Extension
{
    public function getDistName(): string
    {
        return 'swoole';
    }

    public function getUnixConfigureArg(): string
    {
        // pdo_mysql doesn't need to be disabled
        // enable swoole-hook-mysql will enable mysqli, pdo, pdo_mysql, we don't need to add any additional options
        return '';
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
        if (!str_contains($out, 'mysqlnd')) {
            throw new RuntimeException('swoole mysql hook is not enabled correctly.');
        }
    }
}
