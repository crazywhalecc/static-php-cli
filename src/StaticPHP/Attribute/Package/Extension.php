<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Package;

/**
 * Indicates that the annotated class defines a PHP extension.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Extension
{
    public function __construct(public string $name)
    {
        if (!str_starts_with($name, 'ext-')) {
            $this->name = "ext-{$name}";
        }
    }
}
