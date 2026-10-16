<?php

declare(strict_types=1);

namespace StaticPHP\Toolchain\Interface;

/**
 * Optional hook for toolchains that need extra packages in the current environment.
 *
 * Packages declared here are merged into PackageInstaller::resolvePackages() as regular install
 * packages before the dependency graph is resolved, so they follow the normal download, extract,
 * build and install pipeline. That means their own isInstalled() decides whether they are
 * skipped or rebuilt, which is what keeps target-specific artifacts in sync when SPC_TARGET
 * changes between builds. They prefer a published binary over the source, which stays the fallback.
 *
 * This is deliberately separate from ToolchainInterface so existing toolchains do not need to
 * implement it.
 */
interface PackageRequirementsInterface
{
    /**
     * Package names this toolchain needs for the current target.
     *
     * Implementations should return an empty list when the packages cannot be handled in the
     * current run (for example because a prerequisite reported by checkRequirements() is not
     * installed yet), so the caller falls back to whatever installs them first.
     *
     * @return string[] Package names, must be resolvable by PackageLoader
     */
    public function getRequiredPackages(): array;

    /**
     * Prerequisites that must already be satisfied before the packages above can be resolved
     * and downloaded at all (e.g. a tool whose version they are derived from).
     *
     * @return string[] Human-readable unmet prerequisites; empty when everything is ready
     */
    public function checkRequirements(): array;
}
