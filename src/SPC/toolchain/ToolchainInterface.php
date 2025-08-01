<?php

declare(strict_types=1);

namespace SPC\toolchain;

/**
 * Interface for toolchain implementations
 *
 * This interface defines the contract for toolchain classes that handle
 * environment initialization and setup for different build targets.
 */
interface ToolchainInterface
{
    /**
     * Initialize the environment for the given target.
     *
     * This method should set up any necessary environment variables,
     * paths, or configurations required for the build process.
     */
    public function initEnv(): void;

    /**
     * Perform actions after the environment has been initialized for the given target.
     *
     * This method is called after initEnv() and can be used for any
     * post-initialization setup or validation.
     */
    public function afterInit(): void;

    /**
     * Returns the compiler name and version for toolchains.
     *
     * If the toolchain does not support compiler information,
     * this method can return null.
     */
    public function getCompilerInfo(): ?string;
}
