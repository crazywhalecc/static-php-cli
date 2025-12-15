<?php

namespace StaticPHP\Skeleton;

use StaticPHP\Exception\ValidationException;
use StaticPHP\Runtime\Executor\Executor;

class ExecutorGenerator
{
    public function __construct(protected string $class)
    {
        if (!is_a($class, Executor::class, true)) {
            throw new ValidationException('Executor class must extend ' . Executor::class);
        }
    }
}
