<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Package;

/**
 * Indicates that the annotated method should be executed before a specific stage of the build process for a given package.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
readonly class BeforeStage
{
    public function __construct(public string $package_name, public string $stage, public ?string $only_when_package_resolved = null) {}
}
