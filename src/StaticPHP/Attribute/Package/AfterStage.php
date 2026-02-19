<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Package;

/**
 * Indicates that the annotated method should be executed after a specific stage of the build process for a given package.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
readonly class AfterStage
{
    public function __construct(public string $package_name, public array|string $stage, public ?string $only_when_package_resolved = null) {}
}
