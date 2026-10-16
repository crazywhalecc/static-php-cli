<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Runtime;

use PHPUnit\Framework\TestCase;
use StaticPHP\Runtime\SystemTarget;

/**
 * @internal
 */
class SystemTargetTest extends TestCase
{
    private false|string $original_target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->original_target = getenv('SPC_TARGET');
    }

    protected function tearDown(): void
    {
        putenv($this->original_target === false ? 'SPC_TARGET' : "SPC_TARGET={$this->original_target}");
        parent::tearDown();
    }

    public function testNativeTargetIsNotCrossCompiling(): void
    {
        putenv('SPC_TARGET');
        $this->assertFalse(SystemTarget::isCrossCompiling());

        putenv('SPC_TARGET=native-native-musl -dynamic');
        $this->assertFalse(SystemTarget::isCrossCompiling());
    }

    public function testHostArchitectureTripleIsNotCrossCompiling(): void
    {
        putenv('SPC_TARGET=' . GNU_ARCH . '-' . $this->hostTripleSuffix());
        $this->assertFalse(SystemTarget::isCrossCompiling());
    }

    public function testArchitectureAliasMatchesTheHost(): void
    {
        $alias = GNU_ARCH === 'aarch64' ? 'arm64' : 'amd64';
        putenv("SPC_TARGET={$alias}-" . $this->hostTripleSuffix());
        $this->assertFalse(SystemTarget::isCrossCompiling());
    }

    public function testOtherArchitectureIsCrossCompiling(): void
    {
        $other = GNU_ARCH === 'aarch64' ? 'x86_64' : 'aarch64';
        putenv("SPC_TARGET={$other}-" . $this->hostTripleSuffix());
        $this->assertTrue(SystemTarget::isCrossCompiling());
    }

    public function testArchitectureWithoutMappingIsCrossCompiling(): void
    {
        // getTargetArch() falls back to the host for architectures it has no mapping for.
        putenv('SPC_TARGET=arm-linux-musleabihf');
        $this->assertTrue(SystemTarget::isCrossCompiling());
    }

    public function testLibcVersionSuffixIsIgnored(): void
    {
        putenv('SPC_TARGET=' . GNU_ARCH . '-linux-gnu.2.17');
        $this->assertSame(PHP_OS_FAMILY !== 'Linux', SystemTarget::isCrossCompiling());
    }

    public function testOtherOperatingSystemIsCrossCompiling(): void
    {
        $suffix = PHP_OS_FAMILY === 'Linux' ? 'macos' : 'linux-musl';
        putenv('SPC_TARGET=' . GNU_ARCH . "-{$suffix}");
        $this->assertTrue(SystemTarget::isCrossCompiling());
    }

    private function hostTripleSuffix(): string
    {
        return match (PHP_OS_FAMILY) {
            'Linux' => 'linux-musl',
            'Darwin' => 'macos',
            'Windows' => 'windows-gnu',
            default => $this->markTestSkipped('No target triple for host OS ' . PHP_OS_FAMILY),
        };
    }
}
