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

    public function runCliCheckUnix(): void
    {
        [$ret, $out] = shell()->execWithResult(BUILD_ROOT_PATH . '/bin/php -n' . $this->getSharedExtensionLoadString() . ' --ri "swoole"', false);
        $out = implode('', $out);
        if ($ret !== 0) {
            throw new ValidationException("extension {$this->getName()} failed compile check: php-cli returned {$ret}", validation_module: 'extension swoole_hook_mysql sanity check');
        }
        if (!str_contains($out, 'mysqlnd')) {
            throw new ValidationException('swoole mysql hook is not enabled correctly.', validation_module: 'Extension swoole mysql hook availability check');
        }
    }
}
