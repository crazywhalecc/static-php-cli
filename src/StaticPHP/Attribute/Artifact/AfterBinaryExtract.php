<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Artifact;

/**
 * Attribute to register a hook that runs after binary extraction completes.
 *
 * This is useful for post-extraction tasks like:
 * - Setting executable permissions
 * - Creating symlinks
 * - Platform-specific binary setup
 * - Verifying binary integrity
 *
 * The callback method signature should be:
 * ```php
 * function(string $target_path, string $platform): void
 * ```
 *
 * - `$target_path`: The directory where binary was extracted
 * - `$platform`: The current platform string (e.g., 'linux-x86_64')
 *
 * Multiple hooks can be registered for the same artifact using IS_REPEATABLE.
 * Use the `$platforms` parameter to filter which platforms the hook should run on.
 *
 * @example
 * ```php
 * #[AfterBinaryExtract('zig')]
 * public function setupZig(string $target_path, string $platform): void
 * {
 *     // Setup zig after extraction (runs on all platforms)
 *     chmod("{$target_path}/zig", 0755);
 * }
 *
 * #[AfterBinaryExtract('pkg-config', ['linux-x86_64', 'linux-aarch64'])]
 * public function setupPkgConfigLinux(string $target_path): void
 * {
 *     // Linux-specific setup for pkg-config
 *     symlink("{$target_path}/bin/pkg-config", "/usr/local/bin/pkg-config");
 * }
 *
 * #[AfterBinaryExtract('openssl', ['darwin-aarch64'])]
 * public function patchOpensslMacM1(string $target_path): void
 * {
 *     // macOS ARM64 specific patches
 * }
 * ```
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
readonly class AfterBinaryExtract
{
    /**
     * @param string   $artifact_name The name of the artifact this hook applies to
     * @param string[] $platforms     Platform filters (empty array means all platforms).
     *                                Valid values: 'linux-x86_64', 'linux-aarch64', 'darwin-x86_64',
     *                                'darwin-aarch64', 'windows-x86_64', etc.
     */
    public function __construct(
        public string $artifact_name,
        public array $platforms = []
    ) {}
}
