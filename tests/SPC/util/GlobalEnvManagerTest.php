<?php

declare(strict_types=1);

namespace SPC\Tests\util;

use PHPUnit\Framework\TestCase;
use SPC\exception\SPCInternalException;
use SPC\util\GlobalEnvManager;

/**
 * @internal
 */
final class GlobalEnvManagerTest extends TestCase
{
    private array $originalEnv;

    protected function setUp(): void
    {
        // Save original environment variables
        $this->originalEnv = [
            'BUILD_ROOT_PATH' => getenv('BUILD_ROOT_PATH'),
            'SPC_TARGET' => getenv('SPC_TARGET'),
            'SPC_LIBC' => getenv('SPC_LIBC'),
        ];
        // Temporarily set private GlobalEnvManager::$initialized to false (use reflection)
        $reflection = new \ReflectionClass(GlobalEnvManager::class);
        $property = $reflection->getProperty('initialized');
        $property->setValue(null, false);
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
        // Temporarily set private GlobalEnvManager::$initialized to false (use reflection)
        $reflection = new \ReflectionClass(GlobalEnvManager::class);
        $property = $reflection->getProperty('initialized');
        $property->setValue(null, true);
    }

    public function testGetInitializedEnv(): void
    {
        // Test that getInitializedEnv returns an array
        $result = GlobalEnvManager::getInitializedEnv();
        $this->assertIsArray($result);
    }

    /**
     * @dataProvider envVariableProvider
     */
    public function testPutenv(string $envVar): void
    {
        // Test putenv functionality
        GlobalEnvManager::putenv($envVar);

        $env = GlobalEnvManager::getInitializedEnv();
        $this->assertContains($envVar, $env);
        $this->assertEquals(explode('=', $envVar, 2)[1], getenv(explode('=', $envVar, 2)[0]));
    }

    /**
     * @dataProvider pathProvider
     */
    public function testAddPathIfNotExistsOnUnix(string $path): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('This test is for Unix systems only');
        }

        $originalPath = getenv('PATH');
        GlobalEnvManager::addPathIfNotExists($path);

        $newPath = getenv('PATH');
        $this->assertStringContainsString($path, $newPath);
    }

    /**
     * @dataProvider pathProvider
     */
    public function testAddPathIfNotExistsWhenPathAlreadyExists(string $path): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('This test is for Unix systems only');
        }

        GlobalEnvManager::addPathIfNotExists($path);
        $pathAfterFirstAdd = getenv('PATH');

        GlobalEnvManager::addPathIfNotExists($path);
        $pathAfterSecondAdd = getenv('PATH');

        // Should not add the same path twice
        $this->assertEquals($pathAfterFirstAdd, $pathAfterSecondAdd);
    }

    public function testInitWithoutBuildRootPath(): void
    {
        // Temporarily unset BUILD_ROOT_PATH
        putenv('BUILD_ROOT_PATH');

        $this->expectException(SPCInternalException::class);
        GlobalEnvManager::init();
    }

    public function testAfterInit(): void
    {
        // Set required environment variable
        putenv('BUILD_ROOT_PATH=/test/path');
        putenv('SPC_SKIP_TOOLCHAIN_CHECK=true');

        // Should not throw exception when SPC_SKIP_TOOLCHAIN_CHECK is true
        GlobalEnvManager::afterInit();

        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    public function envVariableProvider(): array
    {
        return [
            'simple-env' => ['TEST_VAR=test_value'],
            'complex-env' => ['COMPLEX_VAR=complex_value_with_spaces'],
            'numeric-env' => ['NUMERIC_VAR=123'],
            'special-chars-env' => ['SPECIAL_VAR=test@#$%'],
        ];
    }

    public function pathProvider(): array
    {
        return [
            'simple-path' => ['/test/path'],
            'complex-path' => ['/usr/local/bin'],
            'home-path' => ['/home/user/bin'],
            'root-path' => ['/root/bin'],
        ];
    }
}
