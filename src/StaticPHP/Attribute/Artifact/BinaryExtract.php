<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Artifact;

/**
 * Attribute to completely take over the binary extraction process for an artifact.
 *
 * When this attribute is applied to a method, the standard extraction logic is bypassed,
 * and the annotated method is responsible for extracting the binary files.
 *
 * The callback method signature should be:
 * ```php
 * function(Artifact $artifact, string $source_file, string $target_path, string $platform): void
 * ```
 *
 * - `$artifact`: The artifact instance being extracted
 * - `$source_file`: Path to the downloaded archive or directory
 * - `$target_path`: The resolved target extraction path from config
 * - `$platform`: The current platform string (e.g., 'linux-x86_64', 'darwin-aarch64')
 *
 * @example
 * ```php
 * #[BinaryExtract('special-tool', ['linux-x86_64', 'linux-aarch64'])]
 * public function extractSpecialTool(Artifact $artifact, string $source_file, string $target_path): void
 * {
 *     // Custom binary extraction logic
 * }
 * ```
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
readonly class BinaryExtract
{
    /**
     * @param string   $artifact_name The name of the artifact this extraction handler applies to
     * @param string[] $platforms     Platform filters (empty array means all platforms).
     *                                Valid values: 'linux-x86_64', 'linux-aarch64', 'darwin-x86_64',
     *                                'darwin-aarch64', 'windows-x86_64', etc.
     */
    public function __construct(
        public string $artifact_name,
        public array $platforms = []
    ) {}
}
