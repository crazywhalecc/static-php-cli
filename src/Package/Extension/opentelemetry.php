<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Toolchain\ZigToolchain;
use StaticPHP\Util\GlobalEnvManager;

#[Extension('opentelemetry')]
class opentelemetry
{
    #[BeforeStage('php', [php::class, 'makeForUnix'], 'ext-opentelemetry')]
    public function patchBeforeMake(ToolchainInterface $toolchain): void
    {
        $extra_cflags = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') ?: '';
        $extra_cflags .= ' -Wno-strict-prototypes';
        if ($toolchain instanceof ZigToolchain) {
            $extra_cflags .= ' -Wno-unknown-warning-option';
        }
        GlobalEnvManager::putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS=' . trim($extra_cflags));
    }
}
