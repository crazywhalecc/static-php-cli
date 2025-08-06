<?php

declare(strict_types=1);

namespace SPC\Tests\util;

use SPC\exception\EnvironmentException;
use SPC\util\shell\UnixShell;

/**
 * @internal
 */
final class UnixShellTest extends TestBase
{
    public function testConstructorOnWindows(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('This test is for Windows systems only');
        }

        $this->expectException(EnvironmentException::class);
        $this->expectExceptionMessage('Windows cannot use UnixShell');

        new UnixShell();
    }

    /**
     * @dataProvider envProvider
     */
    public function testSetEnv(array $env): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('This test is for Unix systems only');
        }

        $shell = $this->createUnixShell();
        $result = $shell->setEnv($env);

        $this->assertSame($shell, $result);
        foreach ($env as $item) {
            if (trim($item) !== '') {
                $this->assertStringContainsString($item, $shell->getEnvString());
            }
        }
    }

    /**
     * @dataProvider envProvider
     */
    public function testAppendEnv(array $env): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('This test is for Unix systems only');
        }

        $shell = $this->createUnixShell();
        $shell->setEnv(['CFLAGS' => '-O2']);

        $shell->appendEnv($env);

        $this->assertStringContainsString('-O2', $shell->getEnvString());
        foreach ($env as $value) {
            if (trim($value) !== '') {
                $this->assertStringContainsString($value, $shell->getEnvString());
            }
        }
    }

    /**
     * @dataProvider envProvider
     */
    public function testGetEnvString(array $env): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('This test is for Unix systems only');
        }

        $shell = $this->createUnixShell();
        $shell->setEnv($env);

        $envString = $shell->getEnvString();

        $hasNonEmptyValues = false;
        foreach ($env as $key => $value) {
            if (trim($value) !== '') {
                $this->assertStringContainsString("{$key}=\"{$value}\"", $envString);
                $hasNonEmptyValues = true;
            }
        }

        // If all values are empty, ensure we still have a test assertion
        if (!$hasNonEmptyValues) {
            $this->assertIsString($envString);
        }
    }

    public function testGetEnvStringWithEmptyEnv(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('This test is for Unix systems only');
        }

        $shell = $this->createUnixShell();
        $envString = $shell->getEnvString();

        $this->assertEquals('', trim($envString));
    }

    /**
     * @dataProvider commandProvider
     */
    public function testExecWithResult(string $command): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('This test is for Unix systems only');
        }

        $shell = $this->createUnixShell();
        [$code, $output] = $shell->execWithResult($command);

        $this->assertIsInt($code);
        $this->assertIsArray($output);
    }

    public function testExecWithResultWithLog(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('This test is for Unix systems only');
        }

        $shell = $this->createUnixShell();
        [$code, $output] = $shell->execWithResult('echo "test"', false);

        $this->assertIsInt($code);
        $this->assertIsArray($output);
        $this->assertEquals(0, $code);
        $this->assertEquals(['test'], $output);
    }

    public function testExecWithResultWithCd(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('This test is for Unix systems only');
        }

        $shell = $this->createUnixShell();
        $shell->cd('/tmp');

        [$code, $output] = $shell->execWithResult('pwd');

        $this->assertIsInt($code);
        $this->assertEquals(0, $code);
        $this->assertIsArray($output);
    }

    public static function directoryProvider(): array
    {
        return [
            'simple-directory' => ['/test/directory'],
            'home-directory' => ['/home/user'],
            'root-directory' => ['/root'],
            'tmp-directory' => ['/tmp'],
        ];
    }

    public static function envProvider(): array
    {
        return [
            'simple-env' => [['CFLAGS' => '-O2', 'LDFLAGS' => '-L/usr/lib']],
            'complex-env' => [['CXXFLAGS' => '-std=c++11', 'LIBS' => '-lz -lxml']],
            'empty-env' => [['CFLAGS' => '', 'LDFLAGS' => '   ']],
            'mixed-env' => [['CFLAGS' => '-O2', 'EMPTY_VAR' => '']],
        ];
    }

    public static function commandProvider(): array
    {
        return [
            'echo-command' => ['echo "test"'],
            'pwd-command' => ['pwd'],
            'ls-command' => ['ls -la'],
        ];
    }
}
