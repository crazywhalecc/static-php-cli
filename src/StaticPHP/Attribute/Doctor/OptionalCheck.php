<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Doctor;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class OptionalCheck
{
    public function __construct(public array $check) {}
}
