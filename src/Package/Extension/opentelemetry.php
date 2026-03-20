<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Util\GlobalEnvManager;

#[Extension('opentelemetry')]
class opentelemetry
{
    #[BeforeStage('php', [php::class, 'makeForUnix'], 'ext-opentelemetry')]
    public function patchBeforeMake(): void
    {
        // add -Wno-strict-prototypes
        GlobalEnvManager::putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS=' . getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') . ' -Wno-strict-prototypes');
    }
}
