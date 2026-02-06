<?php

declare(strict_types=1);

namespace Tests\StaticPHP\DI;

use DI\Container;
use PHPUnit\Framework\TestCase;
use StaticPHP\DI\CallbackInvoker;
use StaticPHP\Exception\SPCInternalException;

/**
 * Helper class that requires constructor parameters for testing
 */
class UnresolvableTestClass
{
    public function __construct(
        private string $requiredParam
    ) {}
}

/**
 * @internal
 */
class CallbackInvokerTest extends TestCase
{
    private Container $container;

    private CallbackInvoker $invoker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
        $this->invoker = new CallbackInvoker($this->container);
    }

    public function testInvokeSimpleCallbackWithoutParameters(): void
    {
        $callback = function () {
            return 'result';
        };

        $result = $this->invoker->invoke($callback);

        $this->assertEquals('result', $result);
    }

    public function testInvokeCallbackWithContextByTypeName(): void
    {
        $callback = function (string $param) {
            return $param;
        };

        $result = $this->invoker->invoke($callback, ['string' => 'test_value']);

        $this->assertEquals('test_value', $result);
    }

    public function testInvokeCallbackWithContextByParameterName(): void
    {
        $callback = function (string $myParam) {
            return $myParam;
        };

        $result = $this->invoker->invoke($callback, ['myParam' => 'test_value']);

        $this->assertEquals('test_value', $result);
    }

    public function testInvokeCallbackWithContextByTypeNameTakesPrecedence(): void
    {
        $callback = function (string $myParam) {
            return $myParam;
        };

        // Type name should take precedence over parameter name
        $result = $this->invoker->invoke($callback, [
            'string' => 'by_type',
            'myParam' => 'by_name',
        ]);

        $this->assertEquals('by_type', $result);
    }

    public function testInvokeCallbackWithContainerResolution(): void
    {
        $this->container->set('test.service', 'service_value');

        $callback = function (string $testService) {
            return $testService;
        };

        // Should not resolve from container as 'test.service' is not a type
        // Will try default value or null
        $this->expectException(SPCInternalException::class);
        $this->invoker->invoke($callback);
    }

    public function testInvokeCallbackWithClassTypeFromContainer(): void
    {
        $testObject = new \stdClass();
        $testObject->value = 'test';
        $this->container->set(\stdClass::class, $testObject);

        $callback = function (\stdClass $obj) {
            return $obj->value;
        };

        $result = $this->invoker->invoke($callback);

        $this->assertEquals('test', $result);
    }

    public function testInvokeCallbackWithDefaultValue(): void
    {
        $callback = function (string $param = 'default_value') {
            return $param;
        };

        $result = $this->invoker->invoke($callback);

        $this->assertEquals('default_value', $result);
    }

    public function testInvokeCallbackWithNullableParameter(): void
    {
        $callback = function (?string $param) {
            return $param ?? 'was_null';
        };

        $result = $this->invoker->invoke($callback);

        $this->assertEquals('was_null', $result);
    }

    public function testInvokeCallbackThrowsExceptionForUnresolvableParameter(): void
    {
        $callback = function (string $required) {
            return $required;
        };

        $this->expectException(SPCInternalException::class);
        $this->expectExceptionMessage("Cannot resolve parameter 'required' of type 'string'");
        $this->invoker->invoke($callback);
    }

    public function testInvokeCallbackThrowsExceptionForNonExistentClass(): void
    {
        // This test uses UnresolvableTestClass which has required constructor params
        // Container.has() will return true but get() will throw InvalidDefinition
        // So we test that container exceptions bubble up
        $callback = function (UnresolvableTestClass $obj) {
            return $obj;
        };

        $this->expectException(\Throwable::class);
        $this->invoker->invoke($callback);
    }

    public function testInvokeCallbackWithMultipleParameters(): void
    {
        $callback = function (string $first, int $second, bool $third) {
            return [$first, $second, $third];
        };

        $result = $this->invoker->invoke($callback, [
            'first' => 'value1',
            'second' => 42,
            'third' => true,
        ]);

        $this->assertEquals(['value1', 42, true], $result);
    }

    public function testInvokeCallbackWithMixedResolutionSources(): void
    {
        $this->container->set(\stdClass::class, new \stdClass());

        $callback = function (
            \stdClass $fromContainer,
            string $fromContext,
            int $withDefault = 100
        ) {
            return [$fromContainer, $fromContext, $withDefault];
        };

        $result = $this->invoker->invoke($callback, ['fromContext' => 'context_value']);

        $this->assertInstanceOf(\stdClass::class, $result[0]);
        $this->assertEquals('context_value', $result[1]);
        $this->assertEquals(100, $result[2]);
    }

    public function testExpandContextHierarchyWithObject(): void
    {
        // Create a simple parent-child relationship
        $childClass = new \ArrayObject(['key' => 'value']);

        $callback = function (\ArrayObject $obj) {
            return $obj;
        };

        $result = $this->invoker->invoke($callback, [get_class($childClass) => $childClass]);

        $this->assertSame($childClass, $result);
    }

    public function testExpandContextHierarchyWithInterface(): void
    {
        $object = new class implements \Countable {
            public function count(): int
            {
                return 42;
            }
        };

        $callback = function (\Countable $countable) {
            return $countable->count();
        };

        $result = $this->invoker->invoke($callback, [get_class($object) => $object]);

        $this->assertEquals(42, $result);
    }

    public function testExpandContextHierarchyWithMultipleInterfaces(): void
    {
        $object = new class implements \Countable, \IteratorAggregate {
            public function count(): int
            {
                return 5;
            }

            public function getIterator(): \Traversable
            {
                return new \ArrayIterator([]);
            }
        };

        $callback = function (\Countable $c, \IteratorAggregate $i) {
            return [$c->count(), $i];
        };

        $result = $this->invoker->invoke($callback, ['obj' => $object]);

        $this->assertEquals(5, $result[0]);
        $this->assertInstanceOf(\IteratorAggregate::class, $result[1]);
    }

    public function testInvokeWithArrayCallback(): void
    {
        $testClass = new class {
            public function method(string $param): string
            {
                return 'called_' . $param;
            }
        };

        $result = $this->invoker->invoke([$testClass, 'method'], ['param' => 'test']);

        $this->assertEquals('called_test', $result);
    }

    public function testInvokeWithStaticMethod(): void
    {
        $testClass = new class {
            public static function staticMethod(string $param): string
            {
                return 'static_' . $param;
            }
        };

        $className = get_class($testClass);
        $result = $this->invoker->invoke([$className, 'staticMethod'], ['param' => 'value']);

        $this->assertEquals('static_value', $result);
    }

    public function testInvokeWithCallableString(): void
    {
        $callback = 'Tests\StaticPHP\DI\testFunction';

        if (!function_exists($callback)) {
            eval('namespace Tests\StaticPHP\DI; function testFunction(string $param) { return "func_" . $param; }');
        }

        $result = $this->invoker->invoke($callback, ['param' => 'test']);

        $this->assertEquals('func_test', $result);
    }

    public function testInvokeWithNoTypeHintedParameter(): void
    {
        $callback = function ($param) {
            return $param;
        };

        $result = $this->invoker->invoke($callback, ['param' => 'value']);

        $this->assertEquals('value', $result);
    }

    public function testInvokeWithNoTypeHintedParameterReturnsNull(): void
    {
        // Parameters without type hints are implicitly nullable in PHP
        $callback = function ($param) {
            return $param;
        };

        $result = $this->invoker->invoke($callback);

        $this->assertNull($result);
    }

    public function testInvokeWithNoTypeHintAndValueInContext(): void
    {
        $callback = function ($param) {
            return $param;
        };

        $result = $this->invoker->invoke($callback, ['param' => 'value']);

        $this->assertEquals('value', $result);
    }

    public function testInvokeWithBuiltinTypes(): void
    {
        $callback = function (
            string $str,
            int $num,
            float $decimal,
            bool $flag,
            array $arr
        ) {
            return compact('str', 'num', 'decimal', 'flag', 'arr');
        };

        $result = $this->invoker->invoke($callback, [
            'str' => 'test',
            'num' => 42,
            'decimal' => 3.14,
            'flag' => true,
            'arr' => [1, 2, 3],
        ]);

        $this->assertEquals([
            'str' => 'test',
            'num' => 42,
            'decimal' => 3.14,
            'flag' => true,
            'arr' => [1, 2, 3],
        ], $result);
    }

    public function testInvokeWithEmptyContext(): void
    {
        $callback = function () {
            return 'no_params';
        };

        $result = $this->invoker->invoke($callback, []);

        $this->assertEquals('no_params', $result);
    }

    public function testInvokePreservesCallbackReturnValue(): void
    {
        $callback = function () {
            return ['key' => 'value', 'number' => 123];
        };

        $result = $this->invoker->invoke($callback);

        $this->assertEquals(['key' => 'value', 'number' => 123], $result);
    }

    public function testInvokeWithNullReturnValue(): void
    {
        $callback = function () {
            return null;
        };

        $result = $this->invoker->invoke($callback);

        $this->assertNull($result);
    }

    public function testInvokeWithObjectInContext(): void
    {
        $obj = new \stdClass();
        $obj->value = 'test';

        $callback = function (\stdClass $param) {
            return $param->value;
        };

        $result = $this->invoker->invoke($callback, ['param' => $obj]);

        $this->assertEquals('test', $result);
    }

    public function testInvokeWithInheritanceInContext(): void
    {
        $exception = new \RuntimeException('test message');

        $callback = function (\Exception $e) {
            return $e->getMessage();
        };

        // RuntimeException should be resolved as Exception via hierarchy expansion
        $result = $this->invoker->invoke($callback, ['exc' => $exception]);

        $this->assertEquals('test message', $result);
    }

    public function testInvokeContextValueOverridesContainer(): void
    {
        $containerObj = new \stdClass();
        $containerObj->source = 'container';
        $this->container->set(\stdClass::class, $containerObj);

        $contextObj = new \stdClass();
        $contextObj->source = 'context';

        $callback = function (\stdClass $obj) {
            return $obj->source;
        };

        // Context should override container
        $result = $this->invoker->invoke($callback, [\stdClass::class => $contextObj]);

        $this->assertEquals('context', $result);
    }

    public function testInvokeWithDefaultValueNotUsedWhenContextProvided(): void
    {
        $callback = function (string $param = 'default') {
            return $param;
        };

        $result = $this->invoker->invoke($callback, ['param' => 'from_context']);

        $this->assertEquals('from_context', $result);
    }

    public function testInvokeWithMixedNullableAndRequired(): void
    {
        $callback = function (string $required, ?string $optional) {
            return [$required, $optional];
        };

        $result = $this->invoker->invoke($callback, ['required' => 'value']);

        $this->assertEquals(['value', null], $result);
    }

    public function testInvokeWithComplexObjectHierarchy(): void
    {
        // Use built-in PHP classes with inheritance
        // ArrayIterator extends IteratorIterator implements ArrayAccess, SeekableIterator, Countable, Serializable
        $arrayIterator = new \ArrayIterator(['test' => 'value']);

        // Test that the object can be resolved via interface (Countable)
        $callback1 = function (\Countable $test) {
            return $test->count();
        };

        $result1 = $this->invoker->invoke($callback1, ['obj' => $arrayIterator]);
        $this->assertEquals(1, $result1);

        // Test that the object can be resolved via another interface (Iterator)
        $callback2 = function (\Iterator $test) {
            return $test;
        };

        $result2 = $this->invoker->invoke($callback2, ['obj' => $arrayIterator]);
        $this->assertInstanceOf(\ArrayIterator::class, $result2);

        // Test that the object can be resolved via concrete class
        $callback3 = function (\ArrayIterator $test) {
            return $test;
        };

        $result3 = $this->invoker->invoke($callback3, ['obj' => $arrayIterator]);
        $this->assertSame($arrayIterator, $result3);
    }

    public function testInvokeWithNonObjectContextValues(): void
    {
        $callback = function (string $str, int $num, array $arr, bool $flag) {
            return compact('str', 'num', 'arr', 'flag');
        };

        $context = [
            'str' => 'hello',
            'num' => 999,
            'arr' => ['a', 'b'],
            'flag' => false,
        ];

        $result = $this->invoker->invoke($callback, $context);

        $this->assertEquals($context, $result);
    }

    public function testInvokeParameterOrderMatters(): void
    {
        $callback = function (string $first, string $second, string $third) {
            return [$first, $second, $third];
        };

        $result = $this->invoker->invoke($callback, [
            'first' => 'A',
            'second' => 'B',
            'third' => 'C',
        ]);

        $this->assertEquals(['A', 'B', 'C'], $result);
    }

    public function testInvokeWithUnionTypeThrowsException(): void
    {
        if (PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('Union types require PHP 8.0+');
        }

        $callback = eval('return function (string|int $param) { return $param; };');

        // Union types are not ReflectionNamedType, should not be resolved from container
        $this->expectException(SPCInternalException::class);
        $this->invoker->invoke($callback);
    }

    public function testInvokeWithCallableType(): void
    {
        $callback = function (callable $fn) {
            return $fn();
        };

        $result = $this->invoker->invoke($callback, [
            'fn' => fn () => 'called',
        ]);

        $this->assertEquals('called', $result);
    }

    public function testInvokeWithIterableType(): void
    {
        $callback = function (iterable $items) {
            $result = [];
            foreach ($items as $item) {
                $result[] = $item;
            }
            return $result;
        };

        $result = $this->invoker->invoke($callback, [
            'items' => [1, 2, 3],
        ]);

        $this->assertEquals([1, 2, 3], $result);
    }

    public function testInvokeWithObjectType(): void
    {
        $callback = function (object $obj) {
            return get_class($obj);
        };

        $testObj = new \stdClass();
        $result = $this->invoker->invoke($callback, ['obj' => $testObj]);

        $this->assertEquals('stdClass', $result);
    }

    public function testInvokeWithContainerExceptionFallsThrough(): void
    {
        // Test fix for issue #1 - Container exceptions should be caught
        // and fall through to other resolution strategies
        $callback = function (?UnresolvableTestClass $obj = null) {
            return $obj;
        };

        // Should use default value (null) instead of throwing container exception
        $result = $this->invoker->invoke($callback);

        $this->assertNull($result);
    }

    public function testInvokeWithContainerExceptionAndNoFallback(): void
    {
        // When there's no fallback (no default, not nullable), should throw RuntimeException
        $callback = function (UnresolvableTestClass $obj) {
            return $obj;
        };

        $this->expectException(SPCInternalException::class);
        $this->expectExceptionMessage("Cannot resolve parameter 'obj'");

        $this->invoker->invoke($callback);
    }

    public function testExpandContextHierarchyPerformance(): void
    {
        // Test fix for issue #2 - Should not create duplicate ReflectionClass
        // This is more of a code quality test, ensuring the fix doesn't break functionality
        $obj = new \ArrayIterator(['a', 'b', 'c']);

        $callback = function (
            \ArrayIterator $asArrayIterator,
            \Traversable $asTraversable,
            \Countable $asCountable
        ) {
            return [
                get_class($asArrayIterator),
                get_class($asTraversable),
                get_class($asCountable),
            ];
        };

        $result = $this->invoker->invoke($callback, ['obj' => $obj]);

        $this->assertEquals([
            'ArrayIterator',
            'ArrayIterator',
            'ArrayIterator',
        ], $result);
    }
}
