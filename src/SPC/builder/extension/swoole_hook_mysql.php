<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\ValidationException;
use SPC\util\CustomExt;

#[CustomExt('swoole-hook-mysql')]
class swoole_hook_mysql extends Extension
{
    public function getDistName(): string
    {
        return 'swoole';
    }

    public function getUnixConfigureArg(bool $shared = false): string
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
        [$ret, $out] = shell()->execWithResult(BUILD_ROOT_PATH . '/bin/php -n' . $this->getSharedExtensionLoadString() . ' --ri "swoole"', false);
        $out = implode('', $out);
        if ($ret !== 0) {
            throw new ValidationException("extension {$this->getName()} failed compile check: php-cli returned {$ret}", validation_module: 'extension swoole_hook_mysql sanity check');
        }
        if (!str_contains($out, 'mysqlnd')) {
            throw new ValidationException('swoole mysql hook is not enabled correctly.', validation_module: 'Extension swoole mysql hook availability check');
        }
    }

    public function getSharedExtensionLoadString(): string
    {
        $ret = parent::getSharedExtensionLoadString();
        return str_replace(' -d "extension=' . $this->name . '"', '', $ret);
    }

    public function buildShared(): void
    {
        // nothing to do, it's built into swoole
    }
}
