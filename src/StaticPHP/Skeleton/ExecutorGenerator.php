<?php

declare(strict_types=1);

namespace StaticPHP\Skeleton;

use StaticPHP\Exception\ValidationException;
use StaticPHP\Runtime\Executor\Executor;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;

class ExecutorGenerator
{
    public function __construct(protected string $class)
    {
        if (!is_a($class, Executor::class, true)) {
            throw new ValidationException('Executor class must extend ' . Executor::class);
        }
    }

    /**
     * Generate the code to create an instance of the executor.
     *
     * @return array{0: string, 1: string} an array containing the class name and the code string
     */
    public function generateCode(): array
    {
        return match ($this->class) {
            UnixCMakeExecutor::class => [UnixCMakeExecutor::class, 'UnixCMakeExecutor::create($package)->build();'],
            UnixAutoconfExecutor::class => [UnixAutoconfExecutor::class, 'UnixAutoconfExecutor::create($package)->build();'],
            WindowsCMakeExecutor::class => [WindowsCMakeExecutor::class, 'WindowsCMakeExecutor::create($package)->build();'],
            default => throw new ValidationException("Unsupported executor class: {$this->class}"),
        };
    }
}
