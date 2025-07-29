<?php

declare(strict_types=1);

namespace SPC\Tests\util;

use PHPUnit\Framework\TestCase;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\util\DependencyUtil;

/**
 * @internal
 */
final class DependencyUtilTest extends TestCase
{
    private array $originalConfig;

    protected function setUp(): void
    {
        // Save original configuration
        $this->originalConfig = [
            'source' => Config::$source,
            'lib' => Config::$lib,
            'ext' => Config::$ext,
        ];
    }

    protected function tearDown(): void
    {
        // Restore original configuration
        Config::$source = $this->originalConfig['source'];
        Config::$lib = $this->originalConfig['lib'];
        Config::$ext = $this->originalConfig['ext'];
    }

    public function testGetExtLibsByDeps(): void
    {
        // Set up test data
        Config::$source = [
            'test1' => [
                'type' => 'url',
                'url' => 'https://pecl.php.net/get/APCu',
                'filename' => 'apcu.tgz',
                'license' => [
                    'type' => 'file',
                    'path' => 'LICENSE',
                ],
            ],
        ];
        Config::$lib = [
            'lib-base' => ['type' => 'root'],
            'php' => ['type' => 'root'],
            'libaaa' => [
                'source' => 'test1',
                'static-libs' => ['libaaa.a'],
                'lib-depends' => ['libbbb', 'libccc'],
                'lib-suggests' => ['libeee'],
            ],
            'libbbb' => [
                'source' => 'test1',
                'static-libs' => ['libbbb.a'],
                'lib-suggests' => ['libccc'],
            ],
            'libccc' => [
                'source' => 'test1',
                'static-libs' => ['libccc.a'],
            ],
            'libeee' => [
                'source' => 'test1',
                'static-libs' => ['libeee.a'],
                'lib-suggests' => ['libfff'],
            ],
            'libfff' => [
                'source' => 'test1',
                'static-libs' => ['libfff.a'],
            ],
        ];
        Config::$ext = [
            'ext-a' => [
                'type' => 'builtin',
                'lib-depends' => ['libaaa'],
                'ext-suggests' => ['ext-b'],
            ],
            'ext-b' => [
                'type' => 'builtin',
                'lib-depends' => ['libeee'],
            ],
        ];

        // Test dependency resolution
        [$exts, $libs, $not_included] = DependencyUtil::getExtsAndLibs(['ext-a'], include_suggested_exts: true);
        $this->assertContains('libbbb', $libs);
        $this->assertContains('libccc', $libs);
        $this->assertContains('ext-b', $exts);
        $this->assertContains('ext-b', $not_included);

        // Test dependency order
        $this->assertIsInt($b = array_search('libbbb', $libs));
        $this->assertIsInt($c = array_search('libccc', $libs));
        $this->assertIsInt($a = array_search('libaaa', $libs));
        // libbbb, libaaa
        $this->assertTrue($b < $a);
        $this->assertTrue($c < $a);
        $this->assertTrue($c < $b);
    }

    public function testNotExistExtException(): void
    {
        $this->expectException(WrongUsageException::class);
        DependencyUtil::getExtsAndLibs(['sdsd']);
    }
}
