<?php

declare(strict_types=1);

namespace SPC\Tests\builder\unix;

use PHPUnit\Framework\TestCase;
use SPC\builder\freebsd\SystemUtil as FreebsdSystemUtil;
use SPC\builder\linux\SystemUtil as LinuxSystemUtil;
use SPC\builder\macos\SystemUtil as MacosSystemUtil;
use SPC\exception\FileSystemException;

/**
 * @internal
 */
class UnixSystemUtilTest extends TestCase
{
    private FreebsdSystemUtil|LinuxSystemUtil|MacosSystemUtil $util;

    public function setUp(): void
    {
        $util_class = match (PHP_OS_FAMILY) {
            'Linux' => 'SPC\builder\linux\SystemUtil',
            'Darwin' => 'SPC\builder\macos\SystemUtil',
            'FreeBSD' => 'SPC\builder\freebsd\SystemUtil',
            default => null,
        };
        if ($util_class === null) {
            self::markTestIncomplete('This test is only for Unix');
        }
        $this->util = new $util_class();
    }

    /**
     * @throws FileSystemException
     */
    public function testMakeCmakeToolchainFile()
    {
        $str = $this->util->makeCmakeToolchainFile(PHP_OS_FAMILY, 'x86_64', '');
        $this->assertIsString($str);
    }

    public function testFindCommand()
    {
        $this->assertIsString($this->util->findCommand('bash'));
    }

    public function testMakeEnvVarString()
    {
        $this->assertEquals("PATH='/usr/bin' PKG_CONFIG='/usr/bin/pkg-config'", $this->util->makeEnvVarString(['PATH' => '/usr/bin', 'PKG_CONFIG' => '/usr/bin/pkg-config']));
    }
}
