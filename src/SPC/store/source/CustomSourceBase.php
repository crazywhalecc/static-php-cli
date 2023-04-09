<?php

declare(strict_types=1);

namespace SPC\store\source;

abstract class CustomSourceBase
{
    public const NAME = 'unknown';

    abstract public function fetch();
}
