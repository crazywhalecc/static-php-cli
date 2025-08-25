<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\ValidationException;
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
        if ($this->builder->getExt('pdo_sqlite')?->isBuildStatic()) {
            throw new WrongUsageException('swoole-hook-sqlite provides pdo_sqlite, if you enable sqlite hook for swoole, you must remove pdo_sqlite extension.');
        }
    }

    public function runCliCheckUnix(): void
    {
        $sharedExtensions = $this->getSharedExtensionLoadString();
        [$ret, $out] = shell()->execWithResult(BUILD_BIN_PATH . '/php -n' . $sharedExtensions . ' --ri "' . $this->getDistName() . '"', false);
        $out = implode('', $out);
        if ($ret !== 0) {
            throw new ValidationException("extension {$this->getName()} failed compile check: php-cli returned {$ret}", validation_module: "Extension {$this->getName()} sanity check");
        }
        if (!str_contains($out, 'coroutine_sqlite')) {
            throw new ValidationException('swoole sqlite hook is not enabled correctly.', validation_module: 'Extension swoole sqlite hook availability check');
        }
    }
}
