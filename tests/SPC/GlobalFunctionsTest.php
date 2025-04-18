<?php

declare(strict_types=1);

namespace SPC\Tests;

use PHPUnit\Framework\TestCase;
use SPC\exception\InterruptException;

/**
 * @internal
 */
class GlobalFunctionsTest extends TestCase
{
    public function testMatchPattern(): void
    {
        $this->assertEquals('abc', match_pattern('a*c', 'abc'));
        $this->assertFalse(match_pattern('a*c', 'abcd'));
    }

    public function testFExec(): void
    {
        $this->assertEquals('abc', f_exec('echo abc', $out, $ret));
        $this->assertEquals(0, $ret);
        $this->assertEquals(['abc'], $out);
    }

    public function testPatchPointInterrupt(): void
    {
        $except = patch_point_interrupt(0);
        $this->assertInstanceOf(InterruptException::class, $except);
    }
}
