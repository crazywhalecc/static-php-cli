<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;

#[Extension('curl')]
class curl
{
    #[BeforeStage('php', [php::class, 'makeForWindows'], 'ext-curl')]
    #[PatchDescription('Inject secur32.lib into SPC_EXTRA_LIBS for Schannel SSL support')]
    public function addSecur32LibForWindows(): void
    {
        // curl on Windows uses Schannel (USE_WINDOWS_SSPI=ON, CURL_USE_SCHANNEL=ON),
        // which requires secur32.lib for SSL/TLS functions (SslEncryptPackage, etc.).
        $extra_libs = getenv('SPC_EXTRA_LIBS') ?: '';
        if (!str_contains($extra_libs, 'secur32.lib')) {
            putenv('SPC_EXTRA_LIBS=' . trim($extra_libs . ' secur32.lib'));
        }
    }
}
