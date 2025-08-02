<?php

declare(strict_types=1);

namespace SPC\toolchain;

class MSVCToolchain implements ToolchainInterface
{
    public function initEnv(): void {}

    public function afterInit(): void {}

    public function getCompilerInfo(): ?string
    {
        return null;
    }
}
