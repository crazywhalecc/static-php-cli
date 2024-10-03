<?php

declare(strict_types=1);

namespace SPC\Tests\builder;

use PHPUnit\Framework\TestCase;
use SPC\builder\BuilderBase;
use SPC\builder\BuilderProvider;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * @internal
 */
class BuilderProviderTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        BuilderProvider::makeBuilderByInput(new ArgvInput());
        BuilderProvider::getBuilder();
    }

    public function testMakeBuilderByInput(): void
    {
        $this->assertInstanceOf(BuilderBase::class, BuilderProvider::makeBuilderByInput(new ArgvInput()));
        $this->assertInstanceOf(BuilderBase::class, BuilderProvider::getBuilder());
    }
}
