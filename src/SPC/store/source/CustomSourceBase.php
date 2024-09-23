<?php

declare(strict_types=1);

namespace SPC\store\source;

abstract class CustomSourceBase
{
    public const NAME = 'unknown';

    abstract public function fetch(bool $force = false, ?array $config = null, int $lock_as = SPC_LOCK_SOURCE): void;
}
