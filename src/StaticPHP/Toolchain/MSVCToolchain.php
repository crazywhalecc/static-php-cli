<?php

declare(strict_types=1);

namespace StaticPHP\Toolchain;

use StaticPHP\Toolchain\Interface\ToolchainInterface;

class MSVCToolchain implements ToolchainInterface
{
    public function initEnv(): void {}

    public function afterInit(): void {}

    public function getCompilerInfo(): ?string
    {
        return null;
    }

    public function isStatic(): bool
    {
        return false;
    }
}
