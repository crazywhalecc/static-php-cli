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
    public function testGetExtLibsByDeps(): void
    {
        // example
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
        // test getExtLibsByDeps (notmal test with ext-depends and lib-depends)

        [$exts, $libs, $not_included] = DependencyUtil::getExtsAndLibs(['ext-a'], include_suggested_exts: true);
        $this->assertContains('libbbb', $libs);
        $this->assertContains('libccc', $libs);
        $this->assertContains('ext-b', $exts);
        $this->assertContains('ext-b', $not_included);
        // test dep order
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
