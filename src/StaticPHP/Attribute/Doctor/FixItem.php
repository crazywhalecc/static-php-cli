<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Doctor;

/**
 * Indicate a method is a fix item for doctor check.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class FixItem
{
    public function __construct(public string $name) {}
}
