<?php

declare(strict_types=1);

namespace SPC\Tests\builder\linux;

use PHPUnit\Framework\TestCase;
use SPC\builder\linux\SystemUtil;

/**
 * @internal
 */
class SystemUtilTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            self::markTestIncomplete('This test is only for Linux');
        }
    }

    public function testIsMuslDistAndGetOSRelease()
    {
        $release = SystemUtil::getOSRelease();
        // we cannot ensure what is the current distro, just test the key exists
        $this->assertArrayHasKey('dist', $release);
        $this->assertArrayHasKey('ver', $release);
        $this->assertTrue($release['dist'] === 'alpine' && SystemUtil::isMuslDist() || $release['dist'] !== 'alpine' && !SystemUtil::isMuslDist());
    }

    public function testFindStaticLib()
    {
        $this->assertIsArray(SystemUtil::findStaticLib('ld-linux-x86-64.so.2'));
    }

    public function testGetCpuCount()
    {
        $this->assertIsInt(SystemUtil::getCpuCount());
    }

    public function testFindHeader()
    {
        $this->assertIsArray(SystemUtil::findHeader('elf.h'));
    }

    public function testGetCrossCompilePrefix()
    {
        $this->assertIsString(SystemUtil::getCrossCompilePrefix('gcc', 'x86_64'));
    }

    public function testGetCCType()
    {
        $this->assertEquals('gcc', SystemUtil::getCCType('xjfoiewjfoewof-gcc'));
    }

    public function testGetSupportedDistros()
    {
        $this->assertIsArray(SystemUtil::getSupportedDistros());
    }

    public function testFindHeaders()
    {
        $this->assertIsArray(SystemUtil::findHeaders(['elf.h']));
    }

    public function testFindStaticLibs()
    {
        $this->assertIsArray(SystemUtil::findStaticLibs(['ld-linux-x86-64.so.2']));
    }
}
