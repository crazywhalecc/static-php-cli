<?php

declare(strict_types=1);

namespace StaticPHP\DI;

use DI\Container;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use StaticPHP\Attribute\PatchDescription;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZM\Logger\ConsoleColor;

use function DI\factory;

/**
 * ApplicationContext manages the DI container lifecycle and provides
 * a centralized access point for dependency injection.
 *
 * This replaces the scattered spc_container()->set() calls throughout the codebase.
 */
class ApplicationContext
{
    private static ?Container $container = null;

    private static ?CallbackInvoker $invoker = null;

    private static bool $debug = false;

    /**
     * Initialize the container with configuration.
     * Should only be called once at application startup.
     *
     * @param array $options Initialization options
     *                       - 'debug': Enable debug mode (disables compilation)
     *                       - 'definitions': Additional container definitions
     */
    public static function initialize(array $options = []): Container
    {
        if (self::$container !== null) {
            throw new \RuntimeException('ApplicationContext already initialized. Use reset() first if you need to reinitialize.');
        }

        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(true);

        // Load default definitions
        self::configureDefaults($builder);

        // Add custom definitions if provided
        if (isset($options['definitions']) && is_array($options['definitions'])) {
            $builder->addDefinitions($options['definitions']);
        }

        // Set debug mode
        self::$debug = $options['debug'] ?? false;

        self::$container = $builder->build();
        // Get invoker from container to ensure singleton consistency
        self::$invoker = self::$container->get(CallbackInvoker::class);

        return self::$container;
    }

    /**
     * Get the container instance.
     * If not initialized, initializes with default configuration.
     */
    public static function getContainer(): Container
    {
        if (self::$container === null) {
            self::initialize();
        }
        return self::$container;
    }

    /**
     * Get a service from the container.
     *
     * @template T
     *
     * @param class-string<T> $id Service identifier
     *
     * @return T
     */
    public static function get(string $id): mixed
    {
        return self::getContainer()->get($id);
    }

    /**
     * Check if a service exists in the container.
     */
    public static function has(string $id): bool
    {
        return self::getContainer()->has($id);
    }

    /**
     * Set a service in the container.
     * Use sparingly - prefer configuration-based definitions.
     */
    public static function set(string $id, mixed $value): void
    {
        self::getContainer()->set($id, $value);
    }

    /**
     * Bind command-line context to the container.
     * Called at the start of each command execution.
     */
    public static function bindCommandContext(InputInterface $input, OutputInterface $output): void
    {
        $container = self::getContainer();
        $container->set(InputInterface::class, $input);
        $container->set(OutputInterface::class, $output);
        self::$debug = $output->isDebug();
    }

    /**
     * Get the callback invoker instance.
     */
    public static function getInvoker(): CallbackInvoker
    {
        if (self::$invoker === null) {
            // Get from container to ensure singleton consistency
            self::$invoker = self::getContainer()->get(CallbackInvoker::class);
        }
        return self::$invoker;
    }

    /**
     * Invoke a callback with automatic dependency injection and context.
     *
     * @param callable $callback The callback to invoke
     * @param array    $context  Context parameters for injection
     */
    public static function invoke(callable $callback, array $context = []): mixed
    {
        if (function_exists('logger')) {
            logger()->debug('[INVOKE] ' . (is_array($callback) ? (is_object($callback[0]) ? get_class($callback[0]) : $callback[0]) . '::' . $callback[1] : (is_string($callback) ? $callback : 'Closure')));
        }

        // get if callback has attribute PatchDescription
        $ref = new \ReflectionFunction(\Closure::fromCallable($callback));
        $attributes = $ref->getAttributes(PatchDescription::class);
        foreach ($attributes as $attribute) {
            $attrInstance = $attribute->newInstance();
            if (function_exists('logger')) {
                logger()->info(ConsoleColor::magenta('[PATCH]') . ConsoleColor::green(" {$attrInstance->description}"));
            }
        }
        return self::getInvoker()->invoke($callback, $context);
    }

    /**
     * Check if debug mode is enabled.
     */
    public static function isDebug(): bool
    {
        return self::$debug;
    }

    /**
     * Set debug mode.
     */
    public static function setDebug(bool $debug): void
    {
        self::$debug = $debug;
    }

    /**
     * Reset the container.
     * Primarily used for testing to ensure isolation between tests.
     */
    public static function reset(): void
    {
        self::$container = null;
        self::$invoker = null;
        self::$debug = false;
    }

    /**
     * Configure default container definitions.
     */
    private static function configureDefaults(ContainerBuilder $builder): void
    {
        $builder->addDefinitions([
            // Self-reference for container
            ContainerInterface::class => factory(function (Container $c) {
                return $c;
            }),
            Container::class => factory(function (Container $c) {
                return $c;
            }),

            // CallbackInvoker is created separately to avoid circular dependency
            CallbackInvoker::class => factory(function (Container $c) {
                return new CallbackInvoker($c);
            }),

            // Command context (set at runtime via bindCommandContext)
            InputInterface::class => \DI\value(null),
            OutputInterface::class => \DI\value(null),
        ]);
    }
}
