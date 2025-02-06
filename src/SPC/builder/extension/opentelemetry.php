<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
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
        if (PHP_OS_FAMILY === 'Windows') {
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/ext/opentelemetry/config.w32',
                "EXTENSION('opentelemetry', 'opentelemetry.c otel_observer.c', '/DZEND_ENABLE_STATIC_TSRMLS_CACHE=1');",
                "EXTENSION('opentelemetry', 'opentelemetry.c otel_observer.c', PHP_OPENTELEMETRY_SHARED, '/DZEND_ENABLE_STATIC_TSRMLS_CACHE=1');"
            );
            return true;
        }
        return false;
    }

    public function patchBeforeMake(): bool
    {
        // add -Wno-strict-prototypes
        GlobalEnvManager::putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS=' . getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') . ' -Wno-strict-prototypes');
        return true;
    }
}
