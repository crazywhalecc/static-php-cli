<?php

declare(strict_types=1);

namespace SPC\Tests\store;

use PHPUnit\Framework\TestCase;
use SPC\store\Config;
use SPC\store\FileSystem;

/**
 * @internal
 */
class ConfigTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $testdir = WORKING_DIR . '/.configtest';
        FileSystem::createDir($testdir);
        FileSystem::writeFile($testdir . '/lib.json', file_get_contents(ROOT_DIR . '/config/lib.json'));
        FileSystem::writeFile($testdir . '/ext.json', file_get_contents(ROOT_DIR . '/config/ext.json'));
        FileSystem::writeFile($testdir . '/source.json', file_get_contents(ROOT_DIR . '/config/source.json'));
        FileSystem::loadConfigArray('lib', $testdir);
        FileSystem::loadConfigArray('ext', $testdir);
        FileSystem::loadConfigArray('source', $testdir);
    }

    public static function tearDownAfterClass(): void
    {
        FileSystem::removeDir(WORKING_DIR . '/.configtest');
    }

    public function testGetExts()
    {
        $this->assertTrue(is_assoc_array(Config::getExts()));
    }

    public function testGetLib()
    {
        $this->assertIsArray(Config::getLib('zlib'));
        match (PHP_OS_FAMILY) {
            'FreeBSD', 'Darwin', 'Linux' => $this->assertStringEndsWith('.a', Config::getLib('zlib', 'static-libs', [])[0]),
            'Windows' => $this->assertStringEndsWith('.lib', Config::getLib('zlib', 'static-libs', [])[0]),
            default => null,
        };
    }

    public function testGetExt()
    {
        $this->assertIsArray(Config::getExt('bcmath'));
        $this->assertEquals('builtin', Config::getExt('bcmath', 'type'));
    }

    public function testGetSources()
    {
        $this->assertTrue(is_assoc_array(Config::getSources()));
    }

    public function testGetSource()
    {
        $this->assertIsArray(Config::getSource('php-src'));
    }

    public function testGetLibs()
    {
        $this->assertTrue(is_assoc_array(Config::getLibs()));
    }
}
