<?php

declare(strict_types=1);

namespace SPC\util\toolchain;

interface ToolchainInterface
{
    /**
     * Initialize the environment for the given target.
     */
    public function initEnv(string $target): void;

    /**
     * Perform actions after the environment has been initialized for the given target.
     */
    public function afterInit(string $target): void;
}
