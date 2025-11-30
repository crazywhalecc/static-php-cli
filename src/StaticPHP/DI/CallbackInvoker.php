<?php

declare(strict_types=1);

namespace StaticPHP\DI;

use DI\Container;

/**
 * CallbackInvoker is responsible for invoking callbacks with automatic dependency injection.
 * It supports context-based parameter resolution, allowing temporary bindings without polluting the container.
 */
class CallbackInvoker
{
    public function __construct(
        private Container $container
    ) {}

    /**
     * Invoke a callback with automatic dependency injection.
     *
     * Resolution order for each parameter:
     * 1. Context array (by type name)
     * 2. Context array (by parameter name)
     * 3. Container (by type)
     * 4. Default value
     * 5. Null (if nullable)
     *
     * @param callable $callback The callback to invoke
     * @param array    $context  Context parameters (type => value or name => value)
     *
     * @return mixed The return value of the callback
     *
     * @throws \RuntimeException If a required parameter cannot be resolved
     */
    public function invoke(callable $callback, array $context = []): mixed
    {
        $reflection = new \ReflectionFunction(\Closure::fromCallable($callback));
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;
            $paramName = $param->getName();

            // 1. Look up by type name in context
            if ($typeName !== null && array_key_exists($typeName, $context)) {
                $args[] = $context[$typeName];
                continue;
            }

            // 2. Look up by parameter name in context
            if (array_key_exists($paramName, $context)) {
                $args[] = $context[$paramName];
                continue;
            }

            // 3. Look up in container by type
            if ($typeName !== null && !$this->isBuiltinType($typeName) && $this->container->has($typeName)) {
                $args[] = $this->container->get($typeName);
                continue;
            }

            // 4. Use default value if available
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            // 5. Allow null if nullable
            if ($param->allowsNull()) {
                $args[] = null;
                continue;
            }

            // Cannot resolve parameter
            throw new \RuntimeException(
                "Cannot resolve parameter '{$paramName}'" .
                ($typeName ? " of type '{$typeName}'" : '') .
                ' for callback invocation'
            );
        }

        return $callback(...$args);
    }

    /**
     * Check if a type name is a PHP builtin type.
     */
    private function isBuiltinType(string $typeName): bool
    {
        return in_array($typeName, [
            'string', 'int', 'float', 'bool', 'array',
            'object', 'callable', 'iterable', 'mixed',
            'void', 'null', 'false', 'true', 'never',
        ], true);
    }
}
