<?php

declare(strict_types=1);

namespace SPC\Tests\util;

use SPC\exception\WrongUsageException;
use SPC\util\SPCTarget;

/**
 * @internal
 */
final class SPCTargetTest extends TestBase
{
    private array $originalEnv;

    protected function setUp(): void
    {
        // Save original environment variables
        $this->originalEnv = [
            'SPC_TARGET' => getenv('SPC_TARGET'),
            'SPC_LIBC' => getenv('SPC_LIBC'),
        ];
    }

    protected function tearDown(): void
    {
        // Restore original environment variables
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv("{$key}={$value}");
            }
        }
    }

    /**
     * @dataProvider libcProvider
     */
    public function testIsStatic(string $libc, bool $expected): void
    {
        putenv("SPC_LIBC={$libc}");

        $result = SPCTarget::isStatic();
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider libcProvider
     */
    public function testGetLibc(string $libc, bool $expected): void
    {
        putenv("SPC_LIBC={$libc}");

        $result = SPCTarget::getLibc();
        if ($libc === '') {
            // When SPC_LIBC is set to empty string, getenv returns empty string, not false
            $this->assertEquals('', $result);
        } else {
            $this->assertEquals($libc, $result);
        }
    }

    /**
     * @dataProvider libcProvider
     */
    public function testGetLibcVersion(string $libc): void
    {
        putenv("SPC_LIBC={$libc}");

        $result = SPCTarget::getLibcVersion();
        // The actual result depends on the system, but it could be null if libc is not available
        $this->assertIsStringOrNull($result);
    }

    /**
     * @dataProvider targetOSProvider
     */
    public function testGetTargetOS(string $target, string $expected): void
    {
        putenv("SPC_TARGET={$target}");

        $result = SPCTarget::getTargetOS();
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider invalidTargetProvider
     */
    public function testGetTargetOSWithInvalidTarget(string $target): void
    {
        putenv("SPC_TARGET={$target}");

        $this->expectException(WrongUsageException::class);
        $this->expectExceptionMessage('Cannot parse target.');

        SPCTarget::getTargetOS();
    }

    public function testLibcListConstant(): void
    {
        $this->assertIsArray(SPCTarget::LIBC_LIST);
        $this->assertContains('musl', SPCTarget::LIBC_LIST);
        $this->assertContains('glibc', SPCTarget::LIBC_LIST);
    }

    public function libcProvider(): array
    {
        return [
            'musl' => ['musl', true],
            'glibc' => ['glibc', false],
            'empty' => ['', false],
        ];
    }

    public function targetOSProvider(): array
    {
        return [
            'linux-target' => ['linux-x86_64', 'Linux'],
            'macos-target' => ['macos-x86_64', 'Darwin'],
            'windows-target' => ['windows-x86_64', 'Windows'],
            'empty-target' => ['', PHP_OS_FAMILY],
        ];
    }

    public function invalidTargetProvider(): array
    {
        return [
            'invalid-target' => ['invalid-target'],
            'unknown-target' => ['unknown-target'],
            'mixed-target' => ['mixed-target'],
        ];
    }

    private function assertIsStringOrNull($value): void
    {
        $this->assertTrue(is_string($value) || is_null($value), 'Value must be string or null');
    }
}
