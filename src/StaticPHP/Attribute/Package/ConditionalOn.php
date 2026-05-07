<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Package;

/**
 * Makes a #[BeforeStage] or #[AfterStage] hook conditional on DI container bindings.
 *
 * The hook is only invoked when ALL specified classes are currently bound in the
 * DI container. Multiple #[ConditionalOn] attributes on the same method use AND
 * semantics — every condition must hold for the hook to run.
 *
 * Example:
 *
 *   #[ConditionalOn(PgoContext::class)]
 *   #[BeforeStage('php', 'build')]
 *   public function injectPgoFlags(PgoContext $ctx): void { ... }
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
readonly class ConditionalOn
{
    /**
     * @param class-string $class the class that must be present in the DI container for this hook to run
     */
    public function __construct(public string $class) {}
}
