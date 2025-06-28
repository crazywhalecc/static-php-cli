<?php

declare(strict_types=1);

namespace SPC\util\toolchain;

class MSVCToolchain implements ToolchainInterface
{
    public function initEnv(string $target): void {}

    public function afterInit(string $target): void {}
}
