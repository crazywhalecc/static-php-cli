<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Artifact;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class CustomBinary
{
    public function __construct(public string $artifact_name, public array $support_os) {}
}
