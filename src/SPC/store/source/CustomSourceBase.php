<?php

declare(strict_types=1);

namespace SPC\store\source;

/**
 * Abstract base class for custom source implementations
 *
 * This class provides a framework for implementing custom source download
 * logic. Extend this class to create custom source handlers.
 */
abstract class CustomSourceBase
{
    /**
     * The name of this source implementation
     */
    public const NAME = 'unknown';

    /**
     * Fetch the source from its repository
     *
     * @param bool       $force   Force download even if already exists
     * @param null|array $config  Optional configuration array
     * @param int        $lock_as Lock type constant
     */
    abstract public function fetch(bool $force = false, ?array $config = null, int $lock_as = SPC_DOWNLOAD_SOURCE): void;

    /**
     * Update the source from its repository
     *
     * @param  array      $lock   Lock file entry
     * @param  array      $config Optional configuration array
     * @return null|array Latest version info [url, filename], or null if no update needed
     */
    abstract public function update(array $lock, ?array $config = null): ?array;
}
