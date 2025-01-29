<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\windows\WindowsBuilder;
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
    }

    public function patchBeforeBuildconf(): bool
    {
        // soft link to the grpc source code
        if ($this->builder instanceof WindowsBuilder) {
            // not support windows yet
            throw new \RuntimeException('opentelemetry extension does not support windows yet');
        }
        return false;
    }

    public function patchBeforeMake(): bool
    {
        // add -Wno-strict-prototypes
        GlobalEnvManager::putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS=' . getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') . ' -Wno-strict-prototypes');
        return true;
    }

    public function getUnixConfigureArg(): string
    {
        return '--enable-opentelemetry=' . BUILD_ROOT_PATH . '/opentelemetry';
    }
}
