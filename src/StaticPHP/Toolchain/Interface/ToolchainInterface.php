<?php

declare(strict_types=1);

namespace StaticPHP\Toolchain\Interface;

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

    /**
     * Returns the version of the C compiler this toolchain drives, or null when it cannot be
     * determined.
     *
     * This is deliberately not the toolchain's own version: for zig that is the release of the
     * `zig` binary ("zig 0.17.0" from getCompilerInfo()), while this method reports the clang it
     * bundles (for example "22.1.8"). Toolchain-specific artifacts key on this value because it
     * describes the compiler whose runtime and bitcode they have to match.
     */
    public function getCompilerVersion(): ?string;

    /**
     * Returns whether the toolchain with target is built for full static linking.
     */
    public function isStatic(): bool;
}
