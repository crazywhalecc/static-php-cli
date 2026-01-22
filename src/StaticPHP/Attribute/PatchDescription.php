<?php

declare(strict_types=1);

namespace StaticPHP\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
readonly class PatchDescription
{
    public function __construct(public string $description) {}
}
