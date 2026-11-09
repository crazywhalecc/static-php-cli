<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Runtime\Shell;

use PHPUnit\Framework\TestCase;
use StaticPHP\Runtime\Shell\UnixShell;

/**
 * @internal
 */
class UnixShellTest extends TestCase
{
    protected function setUp(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('UnixShell is not available on Windows');
        }
    }

    public function testExecWithResultReadsOutputWrittenAfterTheCommandExits(): void
    {
        [$code, $output] = new UnixShell(false)->execWithResult("sh -c 'printf first; (sleep 1; printf second) &'", false);

        $this->assertSame(0, $code);
        $this->assertSame(['firstsecond'], $output);
    }
}
