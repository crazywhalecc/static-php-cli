<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Artifact;

/**
 * Attribute to register a hook that runs after source extraction completes.
 *
 * This is useful for post-extraction tasks like:
 * - Patching source files
 * - Removing unnecessary directories (tests, docs, etc.)
 * - Applying platform-specific fixes
 * - Renaming or reorganizing files
 *
 * The callback method signature should be:
 * ```php
 * function(string $target_path): void
 * ```
 *
 * - `$target_path`: The directory where source was extracted
 *
 * Multiple hooks can be registered for the same artifact using IS_REPEATABLE.
 *
 * @example
 * ```php
 * #[AfterSourceExtract('php-src')]
 * public function patchPhpSrc(string $target_path): void
 * {
 *     // Apply patches after php-src is extracted
 *     FileSystem::replaceFileStr("{$target_path}/configure", 'old', 'new');
 * }
 *
 * #[AfterSourceExtract('openssl')]
 * public function cleanupOpenssl(string $target_path): void
 * {
 *     // Remove unnecessary directories
 *     FileSystem::removeDir("{$target_path}/test");
 *     FileSystem::removeDir("{$target_path}/fuzz");
 * }
 * ```
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
readonly class AfterSourceExtract
{
    /**
     * @param string $artifact_name The name of the artifact this hook applies to
     */
    public function __construct(public string $artifact_name) {}
}
