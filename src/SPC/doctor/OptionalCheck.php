<?php

declare(strict_types=1);

namespace SPC\doctor;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class OptionalCheck
{
    public function __construct(public array $check) {}
}
