<?php

declare(strict_types=1);

namespace StaticPHP\Toolchain;

use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\GlobalEnvManager;

class ClangBrewToolchain extends ClangNativeToolchain
{
    public function initEnv(): void
    {
        $homebrew_prefix = getenv('HOMEBREW_PREFIX') ?: (SystemTarget::getTargetArch() === 'aarch64' ? '/opt/homebrew' : '/usr/local/homebrew');
        GlobalEnvManager::putenv("SPC_DEFAULT_CC={$homebrew_prefix}/opt/llvm/bin/clang");
        GlobalEnvManager::putenv("SPC_DEFAULT_CXX={$homebrew_prefix}/opt/llvm/bin/clang++");
        GlobalEnvManager::putenv("SPC_DEFAULT_AR={$homebrew_prefix}/opt/llvm/bin/llvm-ar");
        GlobalEnvManager::putenv('SPC_DEFAULT_LD=ld');
        GlobalEnvManager::addPathIfNotExists("{$homebrew_prefix}/opt/llvm/bin");
    }
}
