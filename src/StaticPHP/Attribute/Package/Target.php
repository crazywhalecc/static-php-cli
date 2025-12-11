<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Package;

/**
 * Indicates that the annotated class defines a target.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
readonly class Target
{
    public function __construct(public string $name) {}
}
