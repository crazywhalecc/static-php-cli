<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

use StaticPHP\Artifact\ArtifactDownloader;

/**
 * Optional hook for download types whose fetched content depends on downloader options
 * rather than only on the artifact config (e.g. php-release varies with --with-php).
 * When the download cache reports an artifact as downloaded, generateQueue() asks the
 * type whether the cached entry still satisfies the current request; returning false
 * forces a re-download.
 *
 * Currently only wired for source locks: no binary download type varies by request
 * options (binary content is expressed by per-platform config keys), but the lock
 * entry shape is identical, so binaries can be hooked in later without signature changes.
 */
interface CacheMatchInterface
{
    /**
     * Check whether a cached lock entry satisfies the current request options.
     *
     * @param string             $name       the name of the artifact
     * @param array              $config     the source configuration for the artifact
     * @param array              $lock_entry the cached lock entry (version, cache_type, ...)
     * @param ArtifactDownloader $downloader the artifact downloader instance
     */
    public function cacheMatches(string $name, array $config, array $lock_entry, ArtifactDownloader $downloader): bool;
}
