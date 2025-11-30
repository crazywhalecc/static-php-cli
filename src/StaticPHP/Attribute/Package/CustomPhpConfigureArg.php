<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Package;

/**
 * Indicates a custom configure argument for PHP build process.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
readonly class CustomPhpConfigureArg
{
    public function __construct(public string $os = '') {}
}
