<?php

declare(strict_types=1);

namespace SPC\Tests\store;

use PHPUnit\Framework\TestCase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

/**
 * @internal
 */
class FileSystemTest extends TestCase
{
    private const TEST_FILE_CONTENT = 'Hello! Bye!';

    public static function setUpBeforeClass(): void
    {
        if (file_put_contents(WORKING_DIR . '/.testfile', self::TEST_FILE_CONTENT) === false) {
            static::markTestSkipped('Current environment or working dir is not writable!');
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (file_exists(WORKING_DIR . '/.testfile')) {
            unlink(WORKING_DIR . '/.testfile');
        }
    }

    /**
     * @throws FileSystemException
     */
    public function testReplaceFileRegex()
    {
        $file = WORKING_DIR . '/.txt1';
        file_put_contents($file, 'hello');

        FileSystem::replaceFileRegex($file, '/ll/', '11');
        $this->assertEquals('he11o', file_get_contents($file));

        unlink($file);
    }

    public function testFindCommandPath()
    {
        $this->assertNull(FileSystem::findCommandPath('randomtestxxxxx'));
        if (PHP_OS_FAMILY === 'Windows') {
            $this->assertIsString(FileSystem::findCommandPath('explorer'));
        } elseif (in_array(PHP_OS_FAMILY, ['Linux', 'Darwin', 'FreeBSD'])) {
            $this->assertIsString(FileSystem::findCommandPath('uname'));
        }
    }

    /**
     * @throws FileSystemException
     */
    public function testReadFile()
    {
        $file = WORKING_DIR . '/.testread';
        file_put_contents($file, 'haha');
        $content = FileSystem::readFile($file);
        $this->assertEquals('haha', $content);
        @unlink($file);
    }

    /**
     * @throws FileSystemException
     */
    public function testReplaceFileUser()
    {
        $file = WORKING_DIR . '/.txt1';
        file_put_contents($file, 'hello');

        FileSystem::replaceFileUser($file, function ($file) {
            return str_replace('el', '55', $file);
        });
        $this->assertEquals('h55lo', file_get_contents($file));

        unlink($file);
    }

    public function testExtname()
    {
        $this->assertEquals('exe', FileSystem::extname('/tmp/asd.exe'));
        $this->assertEquals('', FileSystem::extname('/tmp/asd.'));
    }

    /**
     * @throws \ReflectionException
     * @throws FileSystemException
     */
    public function testGetClassesPsr4()
    {
        $classes = FileSystem::getClassesPsr4(ROOT_DIR . '/src/SPC/builder/extension', 'SPC\builder\extension');
        foreach ($classes as $class) {
            $this->assertIsString($class);
            new \ReflectionClass($class);
        }
    }

    public function testConvertPath()
    {
        $this->assertEquals('phar://C:/pharfile.phar', FileSystem::convertPath('phar://C:/pharfile.phar'));
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->assertEquals('C:\Windows\win.ini', FileSystem::convertPath('C:\Windows/win.ini'));
        }
    }

    /**
     * @throws FileSystemException
     */
    public function testCreateDir()
    {
        FileSystem::createDir(WORKING_DIR . '/.testdir');
        $this->assertDirectoryExists(WORKING_DIR . '/.testdir');
        rmdir(WORKING_DIR . '/.testdir');
    }

    /**
     * @throws FileSystemException
     */
    public function testReplaceFileStr()
    {
        $file = WORKING_DIR . '/.txt1';
        file_put_contents($file, 'hello');

        FileSystem::replaceFileStr($file, 'el', '55');
        $this->assertEquals('h55lo', file_get_contents($file));

        unlink($file);
    }

    /**
     * @throws FileSystemException
     */
    public function testResetDir()
    {
        // prepare fake git dir to test
        FileSystem::createDir(WORKING_DIR . '/.fake_down_test');
        FileSystem::writeFile(WORKING_DIR . '/.fake_down_test/a.c', 'int main() { return 0; }');
        FileSystem::resetDir(WORKING_DIR . '/.fake_down_test');
        $this->assertFileDoesNotExist(WORKING_DIR . '/.fake_down_test/a.c');
        FileSystem::removeDir(WORKING_DIR . '/.fake_down_test');
    }

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function testCopyDir()
    {
        // prepare fake git dir to test
        FileSystem::createDir(WORKING_DIR . '/.fake_down_test');
        FileSystem::writeFile(WORKING_DIR . '/.fake_down_test/a.c', 'int main() { return 0; }');
        FileSystem::copyDir(WORKING_DIR . '/.fake_down_test', WORKING_DIR . '/.fake_down_test2');
        $this->assertDirectoryExists(WORKING_DIR . '/.fake_down_test2');
        $this->assertFileExists(WORKING_DIR . '/.fake_down_test2/a.c');
        FileSystem::removeDir(WORKING_DIR . '/.fake_down_test');
        FileSystem::removeDir(WORKING_DIR . '/.fake_down_test2');
    }

    /**
     * @throws FileSystemException
     */
    public function testRemoveDir()
    {
        FileSystem::createDir(WORKING_DIR . '/.fake_down_test');
        $this->assertDirectoryExists(WORKING_DIR . '/.fake_down_test');
        FileSystem::removeDir(WORKING_DIR . '/.fake_down_test');
        $this->assertDirectoryDoesNotExist(WORKING_DIR . '/.fake_down_test');
    }

    /**
     * @throws FileSystemException
     */
    public function testLoadConfigArray()
    {
        $arr = FileSystem::loadConfigArray('lib');
        $this->assertArrayHasKey('zlib', $arr);
    }

    public function testIsRelativePath()
    {
        $this->assertTrue(FileSystem::isRelativePath('.'));
        $this->assertTrue(FileSystem::isRelativePath('.\sdf'));
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->assertFalse(FileSystem::isRelativePath('C:\asdasd/fwe\asd'));
        } else {
            $this->assertFalse(FileSystem::isRelativePath('/fwefwefewf'));
        }
    }

    public function testScanDirFiles()
    {
        $this->assertFalse(FileSystem::scanDirFiles('wfwefewfewf'));
        $files = FileSystem::scanDirFiles(ROOT_DIR . '/config', true, true);
        $this->assertContains('lib.json', $files);
    }

    /**
     * @throws FileSystemException
     */
    public function testWriteFile()
    {
        FileSystem::writeFile(WORKING_DIR . '/.txt', 'txt');
        $this->assertFileExists(WORKING_DIR . '/.txt');
        $this->assertEquals('txt', FileSystem::readFile(WORKING_DIR . '/.txt'));
        unlink(WORKING_DIR . '/.txt');
    }
}
