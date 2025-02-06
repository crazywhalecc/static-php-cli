<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;
use SPC\util\GlobalEnvManager;

#[CustomExt('opentelemetry')]
class opentelemetry extends Extension
{
    public function validate(): void
    {
        if ($this->builder->getPHPVersionID() < 80000 && getenv('SPC_SKIP_PHP_VERSION_CHECK') !== 'yes') {
            throw new \RuntimeException('The opentelemetry extension requires PHP 8.0 or later');
        }

        if (PHP_OS_FAMILY === 'Windows') {
            throw new \RuntimeException('opentelemetry extension does not support windows yet');
        }
    }

    public function patchBeforeMake(): bool
    {
        // add -Wno-strict-prototypes
        GlobalEnvManager::putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS=' . getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') . ' -Wno-strict-prototypes');
        return true;
    }
}
