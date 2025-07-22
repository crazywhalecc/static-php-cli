<?php

declare(strict_types=1);

namespace SPC\Tests\util;

use PHPUnit\Framework\TestCase;
use SPC\builder\BuilderProvider;
use SPC\exception\FileSystemException;
use SPC\store\FileSystem;
use SPC\util\SPCConfigUtil;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * @internal
 */
class SPCConfigUtilTest extends TestCase
{
    /**
     * @throws FileSystemException
     */
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

    /**
     * @throws FileSystemException
     */
    public static function tearDownAfterClass(): void
    {
        FileSystem::removeDir(WORKING_DIR . '/.configtest');
    }

    public function testConstruct(): void
    {
        $this->assertInstanceOf(SPCConfigUtil::class, new SPCConfigUtil());
        $this->assertInstanceOf(SPCConfigUtil::class, new SPCConfigUtil(BuilderProvider::makeBuilderByInput(new ArgvInput())));
    }

    public function testConfig(): void
    {
        // normal
        $result = (new SPCConfigUtil())->config(['bcmath']);
        $this->assertStringContainsString(BUILD_ROOT_PATH . '/include', $result['cflags']);
        $this->assertStringContainsString(BUILD_ROOT_PATH . '/lib', $result['ldflags']);
        $this->assertStringContainsString('-lphp', $result['libs']);

        // has cpp
        $result = (new SPCConfigUtil())->config(['rar']);
        $this->assertStringContainsString(PHP_OS_FAMILY === 'Darwin' ? '-lc++' : '-lstdc++', $result['libs']);

        // has mimalloc.o in lib dir
        // backup first
        if (file_exists(BUILD_LIB_PATH . '/mimalloc.o')) {
            $bak = file_get_contents(BUILD_LIB_PATH . '/mimalloc.o');
            @unlink(BUILD_LIB_PATH . '/mimalloc.o');
        }
        file_put_contents(BUILD_LIB_PATH . '/mimalloc.o', '');
        $result = (new SPCConfigUtil())->config(['bcmath'], ['mimalloc']);
        $this->assertStringStartsWith(BUILD_LIB_PATH . '/mimalloc.o', $result['libs']);
        @unlink(BUILD_LIB_PATH . '/mimalloc.o');
        if (isset($bak)) {
            file_put_contents(BUILD_LIB_PATH . '/mimalloc.o', $bak);
        }
    }
}
