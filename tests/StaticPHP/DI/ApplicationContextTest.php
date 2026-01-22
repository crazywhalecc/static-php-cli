<?php

declare(strict_types=1);

namespace Tests\StaticPHP\DI;

use DI\Container;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\DI\CallbackInvoker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
class ApplicationContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset ApplicationContext state before each test
        ApplicationContext::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Reset ApplicationContext state after each test
        ApplicationContext::reset();
    }

    public function testInitializeCreatesContainer(): void
    {
        $container = ApplicationContext::initialize();

        $this->assertInstanceOf(Container::class, $container);
        $this->assertSame($container, ApplicationContext::getContainer());
    }

    public function testInitializeWithDebugMode(): void
    {
        ApplicationContext::initialize(['debug' => true]);

        $this->assertTrue(ApplicationContext::isDebug());
    }

    public function testInitializeWithoutDebugMode(): void
    {
        ApplicationContext::initialize(['debug' => false]);

        $this->assertFalse(ApplicationContext::isDebug());
    }

    public function testInitializeWithCustomDefinitions(): void
    {
        $customValue = 'test_value';
        ApplicationContext::initialize([
            'definitions' => [
                'test.service' => $customValue,
            ],
        ]);

        $this->assertEquals($customValue, ApplicationContext::get('test.service'));
    }

    public function testInitializeThrowsExceptionWhenAlreadyInitialized(): void
    {
        ApplicationContext::initialize();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ApplicationContext already initialized');
        ApplicationContext::initialize();
    }

    public function testGetContainerAutoInitializes(): void
    {
        // Don't call initialize
        $container = ApplicationContext::getContainer();

        $this->assertInstanceOf(Container::class, $container);
    }

    public function testGetReturnsServiceFromContainer(): void
    {
        ApplicationContext::initialize([
            'definitions' => [
                'test.key' => 'test_value',
            ],
        ]);

        $this->assertEquals('test_value', ApplicationContext::get('test.key'));
    }

    public function testGetWithClassType(): void
    {
        ApplicationContext::initialize();

        $container = ApplicationContext::get(Container::class);
        $this->assertInstanceOf(Container::class, $container);
    }

    public function testGetContainerInterface(): void
    {
        ApplicationContext::initialize();

        $container = ApplicationContext::get(ContainerInterface::class);
        $this->assertInstanceOf(ContainerInterface::class, $container);
    }

    public function testHasReturnsTrueForExistingService(): void
    {
        ApplicationContext::initialize([
            'definitions' => [
                'test.service' => 'value',
            ],
        ]);

        $this->assertTrue(ApplicationContext::has('test.service'));
    }

    public function testHasReturnsFalseForNonExistingService(): void
    {
        ApplicationContext::initialize();

        $this->assertFalse(ApplicationContext::has('non.existing.service'));
    }

    public function testSetAddsServiceToContainer(): void
    {
        ApplicationContext::initialize();

        ApplicationContext::set('dynamic.service', 'dynamic_value');

        $this->assertTrue(ApplicationContext::has('dynamic.service'));
        $this->assertEquals('dynamic_value', ApplicationContext::get('dynamic.service'));
    }

    public function testSetOverridesExistingService(): void
    {
        ApplicationContext::initialize([
            'definitions' => [
                'test.service' => 'original',
            ],
        ]);

        ApplicationContext::set('test.service', 'updated');

        $this->assertEquals('updated', ApplicationContext::get('test.service'));
    }

    public function testBindCommandContextSetsInputAndOutput(): void
    {
        ApplicationContext::initialize();

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $output->method('isDebug')->willReturn(false);

        ApplicationContext::bindCommandContext($input, $output);

        $this->assertSame($input, ApplicationContext::get(InputInterface::class));
        $this->assertSame($output, ApplicationContext::get(OutputInterface::class));
    }

    public function testBindCommandContextSetsDebugMode(): void
    {
        ApplicationContext::initialize();

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $output->method('isDebug')->willReturn(true);

        ApplicationContext::bindCommandContext($input, $output);

        $this->assertTrue(ApplicationContext::isDebug());
    }

    public function testBindCommandContextWithNonDebugOutput(): void
    {
        ApplicationContext::initialize();

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $output->method('isDebug')->willReturn(false);

        ApplicationContext::bindCommandContext($input, $output);

        $this->assertFalse(ApplicationContext::isDebug());
    }

    public function testGetInvokerReturnsCallbackInvoker(): void
    {
        ApplicationContext::initialize();

        $invoker = ApplicationContext::getInvoker();

        $this->assertInstanceOf(CallbackInvoker::class, $invoker);
    }

    public function testGetInvokerReturnsSameInstance(): void
    {
        ApplicationContext::initialize();

        $invoker1 = ApplicationContext::getInvoker();
        $invoker2 = ApplicationContext::getInvoker();

        $this->assertSame($invoker1, $invoker2);
    }

    public function testGetInvokerAutoInitializesContainer(): void
    {
        // Don't call initialize
        $invoker = ApplicationContext::getInvoker();

        $this->assertInstanceOf(CallbackInvoker::class, $invoker);
    }

    public function testInvokeCallsCallback(): void
    {
        ApplicationContext::initialize();

        $called = false;
        $callback = function () use (&$called) {
            $called = true;
            return 'result';
        };

        $result = ApplicationContext::invoke($callback);

        $this->assertTrue($called);
        $this->assertEquals('result', $result);
    }

    public function testInvokeWithContext(): void
    {
        ApplicationContext::initialize();

        $callback = function (string $param) {
            return $param;
        };

        $result = ApplicationContext::invoke($callback, ['param' => 'test_value']);

        $this->assertEquals('test_value', $result);
    }

    public function testInvokeWithDependencyInjection(): void
    {
        ApplicationContext::initialize();

        $callback = function (Container $container) {
            return $container;
        };

        $result = ApplicationContext::invoke($callback);

        $this->assertInstanceOf(Container::class, $result);
    }

    public function testInvokeWithArrayCallback(): void
    {
        ApplicationContext::initialize();

        $object = new class {
            public function method(): string
            {
                return 'called';
            }
        };

        $result = ApplicationContext::invoke([$object, 'method']);

        $this->assertEquals('called', $result);
    }

    public function testIsDebugDefaultsFalse(): void
    {
        ApplicationContext::initialize();

        $this->assertFalse(ApplicationContext::isDebug());
    }

    public function testSetDebugChangesDebugMode(): void
    {
        ApplicationContext::initialize();

        ApplicationContext::setDebug(true);
        $this->assertTrue(ApplicationContext::isDebug());

        ApplicationContext::setDebug(false);
        $this->assertFalse(ApplicationContext::isDebug());
    }

    public function testResetClearsContainer(): void
    {
        ApplicationContext::initialize();
        ApplicationContext::set('test.service', 'value');

        ApplicationContext::reset();

        // After reset, container should be reinitialized
        $this->assertFalse(ApplicationContext::has('test.service'));
    }

    public function testResetClearsInvoker(): void
    {
        ApplicationContext::initialize();
        $invoker1 = ApplicationContext::getInvoker();

        ApplicationContext::reset();

        $invoker2 = ApplicationContext::getInvoker();
        $this->assertNotSame($invoker1, $invoker2);
    }

    public function testResetClearsDebugMode(): void
    {
        ApplicationContext::initialize(['debug' => true]);
        $this->assertTrue(ApplicationContext::isDebug());

        ApplicationContext::reset();

        // After reset and reinit, debug should be false by default
        ApplicationContext::initialize();
        $this->assertFalse(ApplicationContext::isDebug());
    }

    public function testResetAllowsReinitialize(): void
    {
        ApplicationContext::initialize();
        ApplicationContext::reset();

        // Should not throw exception
        $container = ApplicationContext::initialize(['debug' => true]);

        $this->assertInstanceOf(Container::class, $container);
        $this->assertTrue(ApplicationContext::isDebug());
    }

    public function testCallbackInvokerIsAvailableInContainer(): void
    {
        ApplicationContext::initialize();

        $invoker = ApplicationContext::get(CallbackInvoker::class);

        $this->assertInstanceOf(CallbackInvoker::class, $invoker);
    }

    public function testMultipleGetCallsReturnSameContainer(): void
    {
        $container1 = ApplicationContext::getContainer();
        $container2 = ApplicationContext::getContainer();

        $this->assertSame($container1, $container2);
    }

    public function testInitializeWithEmptyOptions(): void
    {
        $container = ApplicationContext::initialize([]);

        $this->assertInstanceOf(Container::class, $container);
        $this->assertFalse(ApplicationContext::isDebug());
    }

    public function testInitializeWithNullDefinitions(): void
    {
        $container = ApplicationContext::initialize(['definitions' => null]);

        $this->assertInstanceOf(Container::class, $container);
    }

    public function testInitializeWithEmptyDefinitions(): void
    {
        $container = ApplicationContext::initialize(['definitions' => []]);

        $this->assertInstanceOf(Container::class, $container);
    }

    public function testSetBeforeInitializeAutoInitializes(): void
    {
        // Don't call initialize
        ApplicationContext::set('test.service', 'value');

        $this->assertEquals('value', ApplicationContext::get('test.service'));
    }

    public function testHasBeforeInitializeAutoInitializes(): void
    {
        // Don't call initialize, should auto-initialize
        $result = ApplicationContext::has(Container::class);

        $this->assertTrue($result);
    }

    public function testGetBeforeInitializeAutoInitializes(): void
    {
        // Don't call initialize
        $container = ApplicationContext::get(Container::class);

        $this->assertInstanceOf(Container::class, $container);
    }

    public function testInvokerSingletonConsistency(): void
    {
        // Test fix for issue #3 and #4 - Invoker instance consistency
        ApplicationContext::initialize();

        $invoker1 = ApplicationContext::getInvoker();
        $invoker2 = ApplicationContext::get(CallbackInvoker::class);

        // Both should return the same instance
        $this->assertSame($invoker1, $invoker2);
    }

    public function testInvokerSingletonConsistencyAfterReset(): void
    {
        ApplicationContext::initialize();
        $invoker1 = ApplicationContext::getInvoker();

        ApplicationContext::reset();
        ApplicationContext::initialize();

        $invoker2 = ApplicationContext::getInvoker();
        $invoker3 = ApplicationContext::get(CallbackInvoker::class);

        // After reset, should be new instance
        $this->assertNotSame($invoker1, $invoker2);
        // But getInvoker() and container should still be consistent
        $this->assertSame($invoker2, $invoker3);
    }
}
