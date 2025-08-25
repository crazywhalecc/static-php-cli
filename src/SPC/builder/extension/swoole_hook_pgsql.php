<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\ValidationException;
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
        if ($this->builder->getExt('pdo_pgsql')?->isBuildStatic()) {
            throw new WrongUsageException('swoole-hook-pgsql provides pdo_pgsql, if you enable pgsql hook for swoole, you must remove pdo_pgsql extension.');
        }
    }

    public function runCliCheckUnix(): void
    {
        $sharedExtensions = $this->getSharedExtensionLoadString();
        [$ret, $out] = shell()->execWithResult(BUILD_BIN_PATH . '/php -n' . $sharedExtensions . ' --ri "' . $this->getDistName() . '"', false);
        $out = implode('', $out);
        if ($ret !== 0) {
            throw new ValidationException(
                "extension {$this->getName()} failed sanity check: php-cli returned {$ret}",
                validation_module: 'Extension swoole-hook-pgsql sanity check'
            );
        }
        if (!str_contains($out, 'coroutine_pgsql')) {
            throw new ValidationException(
                'swoole pgsql hook is not enabled correctly.',
                validation_module: 'Extension swoole pgsql hook availability check'
            );
        }
    }
}
