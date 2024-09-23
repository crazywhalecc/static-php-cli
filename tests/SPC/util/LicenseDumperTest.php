<?php

declare(strict_types=1);

namespace SPC\Tests\util;

use PHPUnit\Framework\TestCase;
use SPC\store\Config;
use SPC\util\LicenseDumper;

/**
 * @internal
 */
final class LicenseDumperTest extends TestCase
{
    private const DIRECTORY = __DIR__ . '/../../var/license-dump';

    public static function tearDownAfterClass(): void
    {
        @rmdir(self::DIRECTORY);
        @rmdir(dirname(self::DIRECTORY));
    }

    protected function setUp(): void
    {
        @rmdir(self::DIRECTORY);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob(self::DIRECTORY . '/*.txt'));
    }

    public function testDumpWithSingleLicense(): void
    {
        Config::$lib = [
            'fake_lib' => [
                'source' => 'fake_lib',
            ],
        ];
        Config::$source = [
            'fake_lib' => [
                'license' => [
                    'type' => 'text',
                    'text' => 'license',
                ],
            ],
        ];

        $dumper = new LicenseDumper();
        $dumper->addLibs(['fake_lib']);
        $dumper->dump(self::DIRECTORY);

        $this->assertFileExists(self::DIRECTORY . '/lib_fake_lib_0.txt');
    }

    public function testDumpWithMultipleLicenses(): void
    {
        Config::$lib = [
            'fake_lib' => [
                'source' => 'fake_lib',
            ],
        ];
        Config::$source = [
            'fake_lib' => [
                'license' => [
                    [
                        'type' => 'text',
                        'text' => 'license',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'license',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'license',
                    ],
                ],
            ],
        ];

        $dumper = new LicenseDumper();
        $dumper->addLibs(['fake_lib']);
        $dumper->dump(self::DIRECTORY);

        $this->assertFileExists(self::DIRECTORY . '/lib_fake_lib_0.txt');
        $this->assertFileExists(self::DIRECTORY . '/lib_fake_lib_1.txt');
        $this->assertFileExists(self::DIRECTORY . '/lib_fake_lib_2.txt');
    }
}
