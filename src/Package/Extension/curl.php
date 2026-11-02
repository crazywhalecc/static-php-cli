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
    #[PatchDescription('Inject secur32.lib into SPC_EXTRA_LIBS for Windows SSPI support')]
    public function addSecur32LibForWindows(): void
    {
        // libcurl on Windows is built with CURL_WINDOWS_SSPI=ON (NTLM/SPNEGO/SMB), which
        // requires secur32.lib for the SSPI functions.
        $extra_libs = getenv('SPC_EXTRA_LIBS') ?: '';
        if (!str_contains($extra_libs, 'secur32.lib')) {
            putenv('SPC_EXTRA_LIBS=' . trim("{$extra_libs} secur32.lib"));
        }
    }
}
