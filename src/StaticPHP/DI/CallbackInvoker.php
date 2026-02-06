<?php

declare(strict_types=1);

namespace StaticPHP\DI;

use DI\Container;
use StaticPHP\Exception\SkipException;
use StaticPHP\Exception\SPCInternalException;

/**
 * CallbackInvoker is responsible for invoking callbacks with automatic dependency injection.
 * It supports context-based parameter resolution, allowing temporary bindings without polluting the container.
 */
readonly class CallbackInvoker
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
     * Note: For object values in context, the invoker automatically registers
     * the object under all its parent classes and interfaces, allowing type hints
     * to match any type in the inheritance hierarchy.
     *
     * @param callable $callback The callback to invoke
     * @param array    $context  Context parameters (type => value or name => value)
     *
     * @return mixed The return value of the callback
     */
    public function invoke(callable $callback, array $context = []): mixed
    {
        // Expand context to include all parent classes and interfaces for objects
        $context = $this->expandContextHierarchy($context);

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
                try {
                    $args[] = $this->container->get($typeName);
                    continue;
                } catch (\Throwable $e) {
                    // Container failed to resolve (e.g., missing constructor params)
                    // Fall through to try default value or nullable
                }
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
            throw new SPCInternalException(
                "Cannot resolve parameter '{$paramName}'" .
                ($typeName ? " of type '{$typeName}'" : '') .
                ' for callback invocation'
            );
        }

        try {
            return $callback(...$args);
        } catch (SkipException $e) {
            logger()->debug("Skipped invocation: {$e->getMessage()}");
            return null;
        }
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

    /**
     * Expand context to include all parent classes and interfaces for object values.
     * This allows type hints to match any type in the object's inheritance hierarchy.
     *
     * @param  array $context Original context array
     * @return array Expanded context with all class hierarchy mappings
     */
    private function expandContextHierarchy(array $context): array
    {
        $expanded = [];

        foreach ($context as $key => $value) {
            // Keep the original key-value pair
            $expanded[$key] = $value;

            // If value is an object, add mappings for all parent classes and interfaces
            if (is_object($value)) {
                $originalReflection = new \ReflectionClass($value);

                // Add concrete class
                $expanded[$originalReflection->getName()] = $value;

                // Add all parent classes
                $reflection = $originalReflection;
                while ($parent = $reflection->getParentClass()) {
                    $expanded[$parent->getName()] = $value;
                    $reflection = $parent;
                }

                // Add all interfaces - reuse original reflection
                $interfaces = $originalReflection->getInterfaceNames();
                foreach ($interfaces as $interface) {
                    $expanded[$interface] = $value;
                }
            }
        }

        return $expanded;
    }
}
