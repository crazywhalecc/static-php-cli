<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Artifact;

/**
 * Attribute to completely take over the source extraction process for an artifact.
 *
 * When this attribute is applied to a method, the standard extraction logic is bypassed,
 * and the annotated method is responsible for extracting the source files.
 *
 * The callback method signature should be:
 * ```php
 * function(Artifact $artifact, string $source_file, string $target_path): void
 * ```
 *
 * - `$artifact`: The artifact instance being extracted
 * - `$source_file`: Path to the downloaded archive or directory
 * - `$target_path`: The resolved target extraction path from config
 *
 * @example
 * ```php
 * #[SourceExtract('weird-package')]
 * public function extractWeirdPackage(Artifact $artifact, string $source_file, string $target_path): void
 * {
 *     // Custom extraction logic
 *     shell_exec("tar -xf {$source_file} -C {$target_path} --strip-components=2");
 * }
 * ```
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
readonly class SourceExtract
{
    /**
     * @param string $artifact_name The name of the artifact this extraction handler applies to
     */
    public function __construct(public string $artifact_name) {}
}
