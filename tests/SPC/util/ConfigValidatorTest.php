<?php

declare(strict_types=1);

namespace SPC\Tests\util;

use PHPUnit\Framework\TestCase;
use SPC\exception\ValidationException;
use SPC\util\ConfigValidator;

/**
 * @internal
 */
class ConfigValidatorTest extends TestCase
{
    public function testValidateSourceGood(): void
    {
        $good_source = [
            'source1' => [
                'type' => 'filelist',
                'url' => 'https://example.com',
                'regex' => '.*',
            ],
            'source2' => [
                'type' => 'git',
                'url' => 'https://example.com',
                'rev' => 'master',
            ],
            'source3' => [
                'type' => 'ghtagtar',
                'repo' => 'aaaa/bbbb',
            ],
            'source4' => [
                'type' => 'ghtar',
                'repo' => 'aaa/bbb',
                'path' => 'path/to/dir',
            ],
            'source5' => [
                'type' => 'ghrel',
                'repo' => 'aaa/bbb',
                'match' => '.*',
            ],
            'source6' => [
                'type' => 'url',
                'url' => 'https://example.com',
            ],
        ];
        try {
            ConfigValidator::validateSource($good_source);
            $this->assertTrue(true);
        } catch (ValidationException $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testValidateSourceBad(): void
    {
        $bad_source = [
            'source1' => [
                'type' => 'filelist',
                'url' => 'https://example.com',
                // no regex
            ],
            'source2' => [
                'type' => 'git',
                'url' => true, // not string
                'rev' => 'master',
            ],
            'source3' => [
                'type' => 'ghtagtar',
                'url' => 'aaaa/bbbb', // not repo
            ],
            'source4' => [
                'type' => 'ghtar',
                'repo' => 'aaa/bbb',
                'path' => true, // not string
            ],
            'source5' => [
                'type' => 'ghrel',
                'repo' => 'aaa/bbb',
                'match' => 1, // not string
            ],
            'source6' => [
                'type' => 'url', // no url
            ],
        ];
        foreach ($bad_source as $name => $src) {
            try {
                ConfigValidator::validateSource([$name => $src]);
                $this->fail("should throw ValidationException for source {$name}");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function testValidateLibsGood(): void
    {
        $good_libs = [
            'lib1' => [
                'source' => 'source1',
            ],
            'lib2' => [
                'source' => 'source2',
                'lib-depends' => [
                    'lib1',
                ],
            ],
            'lib3' => [
                'source' => 'source3',
                'lib-suggests' => [
                    'lib1',
                ],
            ],
        ];
        try {
            ConfigValidator::validateLibs($good_libs, ['source1' => [], 'source2' => [], 'source3' => []]);
            $this->assertTrue(true);
        } catch (ValidationException $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testValidateLibsBad(): void
    {
        // lib.json is broken
        try {
            ConfigValidator::validateLibs('not array');
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        // lib source not exists
        try {
            ConfigValidator::validateLibs(['lib1' => ['source' => 'source3']], ['source1' => [], 'source2' => []]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        // source must be string
        try {
            ConfigValidator::validateLibs(['lib1' => ['source' => true]], ['source1' => [], 'source2' => []]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        // lib-depends must be list
        try {
            ConfigValidator::validateLibs(['lib1' => ['source' => 'source1', 'lib-depends' => ['a' => 'not list']]], ['source1' => [], 'source2' => []]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        // lib-suggests must be list
        try {
            ConfigValidator::validateLibs(['lib1' => ['source' => 'source1', 'lib-suggests' => ['a' => 'not list']]], ['source1' => [], 'source2' => []]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    /**
     * @throws ValidationException
     */
    public function testValidateExts(): void
    {
        ConfigValidator::validateExts([]);
        $this->expectException(ValidationException::class);
        ConfigValidator::validateExts(null);
    }
}
