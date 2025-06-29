<?php

declare(strict_types=1);

namespace SPC\toolchain;

interface ToolchainInterface
{
    /**
     * Initialize the environment for the given target.
     */
    public function initEnv(): void;

    /**
     * Perform actions after the environment has been initialized for the given target.
     */
    public function afterInit(): void;
}
