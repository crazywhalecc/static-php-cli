<?php

declare(strict_types=1);

namespace SPC\toolchain;

class ZigToolchain implements ToolchainInterface
{
    public function initEnv(): void {}

    public function afterInit(): void {}
}
