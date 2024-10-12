<?php

declare(strict_types=1);

namespace SPC\Tests\builder\macos;

use PHPUnit\Framework\TestCase;
use SPC\builder\macos\SystemUtil;

/**
 * @internal
 */
class SystemUtilTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            self::markTestIncomplete('This test is only for macOS');
        }
    }

    public function testGetCpuCount()
    {
        $this->assertIsInt(SystemUtil::getCpuCount());
    }

    public function testGetArchCFlags()
    {
        $this->assertEquals('--target=x86_64-apple-darwin', SystemUtil::getArchCFlags('x86_64'));
    }
}
