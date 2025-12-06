<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Package;

/**
 * Mark a method as building for a specific OS.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
readonly class BuildFor
{
    /**
     * @param 'Darwin'|'Linux'|'Windows' $os The operating system to build for PHP_OS_FAMILY
     */
    public function __construct(public string $os) {}
}
