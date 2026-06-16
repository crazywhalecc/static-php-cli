<?php

declare(strict_types=1);

namespace StaticPHP\Toolchain;

use StaticPHP\Util\GlobalEnvManager;

class ClangPortsToolchain extends ClangNativeToolchain
{
    public function initEnv(): void
    {
        $macports_prefix = getenv('MACPORTS_PREFIX') ?: '/opt/local';
        GlobalEnvManager::putenv("SPC_DEFAULT_CC={$macports_prefix}/bin/clang");
        GlobalEnvManager::putenv("SPC_DEFAULT_CXX={$macports_prefix}/bin/clang++");
        GlobalEnvManager::putenv("SPC_DEFAULT_AR={$macports_prefix}/bin/llvm-ar");
        GlobalEnvManager::putenv('SPC_DEFAULT_LD=ld');
        GlobalEnvManager::addPathIfNotExists("{$macports_prefix}/bin");
    }
}
