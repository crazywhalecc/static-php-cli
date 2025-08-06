<?php

declare(strict_types=1);

namespace SPC\Tests\util;

use SPC\exception\SPCInternalException;
use SPC\util\shell\WindowsCmd;

/**
 * @internal
 */
final class WindowsCmdTest extends TestBase
{
    public function testConstructorOnUnix(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('This test is for Unix systems only');
        }

        $this->expectException(SPCInternalException::class);
        $this->expectExceptionMessage('Only windows can use WindowsCmd');

        new WindowsCmd();
    }

    /**
     * @dataProvider commandProvider
     */
    public function testExecWithResult(string $command): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('This test is for Windows systems only');
        }

        $cmd = $this->createWindowsCmd();
        [$code, $output] = $cmd->execWithResult($command);

        $this->assertIsInt($code);
        $this->assertEquals(0, $code);
        $this->assertIsArray($output);
        $this->assertNotEmpty($output);
    }

    public function testExecWithResultWithLog(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('This test is for Windows systems only');
        }

        $cmd = $this->createWindowsCmd();
        [$code, $output] = $cmd->execWithResult('echo test', false);

        $this->assertIsInt($code);
        $this->assertIsArray($output);
        $this->assertEquals(0, $code);
        $this->assertEquals(['test'], $output);
    }

    public static function commandProvider(): array
    {
        return [
            'echo-command' => ['echo test'],
            'dir-command' => ['dir'],
            'cd-command' => ['cd'],
        ];
    }
}
