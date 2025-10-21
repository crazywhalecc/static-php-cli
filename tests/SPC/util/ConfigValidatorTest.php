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
            'source7' => [
                'type' => 'url',
                'url' => 'https://example.com',
                'filename' => 'test.tar.gz',
                'path' => 'test/path',
                'provide-pre-built' => true,
                'license' => [
                    'type' => 'file',
                    'path' => 'LICENSE',
                ],
            ],
            'source8' => [
                'type' => 'url',
                'url' => 'https://example.com',
                'alt' => [
                    'type' => 'url',
                    'url' => 'https://alt.example.com',
                ],
            ],
            'source9' => [
                'type' => 'url',
                'url' => 'https://example.com',
                'alt' => false,
                'license' => [
                    'type' => 'text',
                    'text' => 'MIT License',
                ],
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
            'source7' => [
                'type' => 'url',
                'url' => 'https://example.com',
                'provide-pre-built' => 'not boolean', // not boolean
            ],
            'source8' => [
                'type' => 'url',
                'url' => 'https://example.com',
                'prefer-stable' => 'not boolean', // not boolean
            ],
            'source9' => [
                'type' => 'url',
                'url' => 'https://example.com',
                'license' => 'not object', // not object
            ],
            'source10' => [
                'type' => 'url',
                'url' => 'https://example.com',
                'license' => [
                    'type' => 'invalid', // invalid type
                ],
            ],
            'source11' => [
                'type' => 'url',
                'url' => 'https://example.com',
                'license' => [
                    'type' => 'file', // missing path
                ],
            ],
            'source12' => [
                'type' => 'url',
                'url' => 'https://example.com',
                'license' => [
                    'type' => 'text', // missing text
                ],
            ],
            'source13' => [
                'type' => 'url',
                'url' => 'https://example.com',
                'alt' => 'not object or boolean', // not object or boolean
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
            'lib4' => [
                'source' => 'source4',
                'headers' => [
                    'header1.h',
                    'header2.h',
                ],
                'headers-windows' => [
                    'windows_header.h',
                ],
                'bin-unix' => [
                    'binary1',
                    'binary2',
                ],
                'frameworks' => [
                    'CoreFoundation',
                    'SystemConfiguration',
                ],
            ],
            'lib5' => [
                'type' => 'package',
                'source' => 'source5',
                'pkg-configs' => [
                    'pkg1',
                    'pkg2',
                ],
            ],
            'lib6' => [
                'type' => 'root',
            ],
        ];
        try {
            ConfigValidator::validateLibs($good_libs, ['source1' => [], 'source2' => [], 'source3' => [], 'source4' => [], 'source5' => []]);
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
        // lib.json is broken by not assoc array
        try {
            ConfigValidator::validateLibs(['lib1', 'lib2'], ['source1' => [], 'source2' => []]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        // lib.json lib is not one of "lib", "package", "root", "target"
        try {
            ConfigValidator::validateLibs(['lib1' => ['source' => 'source1', 'type' => 'not one of']], ['source1' => [], 'source2' => []]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        // lib.json lib if it is "lib" or "package", it must have "source"
        try {
            ConfigValidator::validateLibs(['lib1' => ['type' => 'lib']], ['source1' => [], 'source2' => []]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        // lib.json static-libs must be a list
        try {
            ConfigValidator::validateLibs(['lib1' => ['source' => 'source1', 'static-libs-windows' => 'not list']], ['source1' => [], 'source2' => []]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        // lib.json frameworks must be a list
        try {
            ConfigValidator::validateLibs(['lib1' => ['source' => 'source1', 'frameworks' => 'not list']], ['source1' => [], 'source2' => []]);
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
        // headers must be list
        try {
            ConfigValidator::validateLibs(['lib1' => ['source' => 'source1', 'headers' => 'not list']], ['source1' => [], 'source2' => []]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        // bin must be list
        try {
            ConfigValidator::validateLibs(['lib1' => ['source' => 'source1', 'bin-unix' => 'not list']], ['source1' => [], 'source2' => []]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function testValidateExts(): void
    {
        // Test valid extensions
        $valid_exts = [
            'ext1' => [
                'type' => 'builtin',
            ],
            'ext2' => [
                'type' => 'external',
                'source' => 'source1',
            ],
            'ext3' => [
                'type' => 'external',
                'source' => 'source2',
                'arg-type' => 'enable',
                'lib-depends' => ['lib1'],
                'lib-suggests' => ['lib2'],
                'ext-depends-windows' => ['ext1'],
                'support' => [
                    'Windows' => 'wip',
                    'BSD' => 'wip',
                ],
                'notes' => true,
            ],
            'ext4' => [
                'type' => 'external',
                'source' => 'source3',
                'arg-type-unix' => 'with-path',
                'arg-type-windows' => 'with',
            ],
        ];
        ConfigValidator::validateExts($valid_exts);

        // Test invalid data
        $this->expectException(ValidationException::class);
        ConfigValidator::validateExts(null);
    }

    public function testValidateExtsBad(): void
    {
        // Test invalid extension type
        try {
            ConfigValidator::validateExts(['ext1' => ['type' => 'invalid']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test external extension without source
        try {
            ConfigValidator::validateExts(['ext1' => ['type' => 'external']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test non-object extension
        try {
            ConfigValidator::validateExts(['ext1' => 'not object']);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test invalid source type
        try {
            ConfigValidator::validateExts(['ext1' => ['type' => 'external', 'source' => true]]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test invalid support
        try {
            ConfigValidator::validateExts(['ext1' => ['type' => 'builtin', 'support' => 'not object']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test invalid notes
        try {
            ConfigValidator::validateExts(['ext1' => ['type' => 'builtin', 'notes' => 'not boolean']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test invalid lib-depends
        try {
            ConfigValidator::validateExts(['ext1' => ['type' => 'builtin', 'lib-depends' => 'not list']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test invalid arg-type
        try {
            ConfigValidator::validateExts(['ext1' => ['type' => 'builtin', 'arg-type' => 'invalid']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test invalid arg-type with suffix
        try {
            ConfigValidator::validateExts(['ext1' => ['type' => 'builtin', 'arg-type-unix' => 'invalid']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function testValidatePkgs(): void
    {
        // Test valid packages (all supported types)
        $valid_pkgs = [
            'pkg1' => [
                'type' => 'url',
                'url' => 'https://example.com/file.tar.gz',
            ],
            'pkg2' => [
                'type' => 'ghrel',
                'repo' => 'owner/repo',
                'match' => 'file.+\.tar\.gz',
            ],
            'pkg3' => [
                'type' => 'custom',
            ],
            'pkg4' => [
                'type' => 'url',
                'url' => 'https://example.com/archive.zip',
                'filename' => 'archive.zip',
                'path' => 'extract/path',
                'extract-files' => [
                    'source/file.exe' => '{pkg_root_path}/bin/file.exe',
                    'source/lib.dll' => '{pkg_root_path}/lib/lib.dll',
                ],
            ],
            'pkg5' => [
                'type' => 'ghrel',
                'repo' => 'owner/repo',
                'match' => 'release.+\.zip',
                'extract-files' => [
                    'binary' => '{pkg_root_path}/bin/binary',
                ],
            ],
            'pkg6' => [
                'type' => 'filelist',
                'url' => 'https://example.com/filelist',
                'regex' => '/href="(?<file>.*\.tar\.gz)"/',
            ],
            'pkg7' => [
                'type' => 'git',
                'url' => 'https://github.com/owner/repo.git',
                'rev' => 'main',
            ],
            'pkg8' => [
                'type' => 'git',
                'url' => 'https://github.com/owner/repo.git',
                'rev' => 'v1.0.0',
                'path' => 'subdir/path',
            ],
            'pkg9' => [
                'type' => 'ghtagtar',
                'repo' => 'owner/repo',
            ],
            'pkg10' => [
                'type' => 'ghtar',
                'repo' => 'owner/repo',
                'path' => 'subdir',
            ],
        ];
        ConfigValidator::validatePkgs($valid_pkgs);

        // Test invalid data
        $this->expectException(ValidationException::class);
        ConfigValidator::validatePkgs(null);
    }

    public function testValidatePkgsBad(): void
    {
        // Test invalid package type
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'invalid']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test non-object package
        try {
            ConfigValidator::validatePkgs(['pkg1' => 'not object']);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test filelist type without url
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'filelist', 'regex' => '.*']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test filelist type without regex
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'filelist', 'url' => 'https://example.com']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test git type without url
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'git', 'rev' => 'main']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test git type without rev
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'git', 'url' => 'https://github.com/owner/repo.git']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test ghtagtar type without repo
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'ghtagtar']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test ghtar type without repo
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'ghtar']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test url type without url
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'url']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test url type with non-string url
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'url', 'url' => true]]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test ghrel type without repo
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'ghrel', 'match' => 'pattern']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test ghrel type without match
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'ghrel', 'repo' => 'owner/repo']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test ghrel type with non-string repo
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'ghrel', 'repo' => true, 'match' => 'pattern']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test ghrel type with non-string match
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'ghrel', 'repo' => 'owner/repo', 'match' => 123]]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test git type with non-string path
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'git', 'url' => 'https://github.com/owner/repo.git', 'rev' => 'main', 'path' => 123]]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test url type with non-string filename
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'url', 'url' => 'https://example.com', 'filename' => 123]]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test invalid extract-files (not object)
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'url', 'url' => 'https://example.com', 'extract-files' => 'not object']]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test invalid extract-files mapping (non-string key)
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'url', 'url' => 'https://example.com', 'extract-files' => [123 => 'target']]]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test invalid extract-files mapping (non-string value)
        try {
            ConfigValidator::validatePkgs(['pkg1' => ['type' => 'url', 'url' => 'https://example.com', 'extract-files' => ['source' => 123]]]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function testValidatePreBuilt(): void
    {
        // Test valid pre-built configurations
        $valid_prebuilt = [
            'basic' => [
                'repo' => 'static-php/static-php-cli-hosted',
                'match-pattern-linux' => '{name}-{arch}-{os}-{libc}-{libcver}.txz',
            ],
            'full' => [
                'repo' => 'static-php/static-php-cli-hosted',
                'prefer-stable' => true,
                'match-pattern-linux' => '{name}-{arch}-{os}-{libc}-{libcver}.txz',
                'match-pattern-macos' => '{name}-{arch}-{os}.txz',
                'match-pattern-windows' => '{name}-{arch}-{os}.tgz',
            ],
            'prefer-stable-false' => [
                'repo' => 'owner/repo',
                'prefer-stable' => false,
                'match-pattern-macos' => '{name}-{arch}-{os}.tar.gz',
            ],
        ];

        foreach ($valid_prebuilt as $name => $config) {
            try {
                ConfigValidator::validatePreBuilt($config);
                $this->assertTrue(true, "Config {$name} should be valid");
            } catch (ValidationException $e) {
                $this->fail("Config {$name} should be valid but got: " . $e->getMessage());
            }
        }
    }

    public function testValidatePreBuiltBad(): void
    {
        // Test non-array data
        try {
            ConfigValidator::validatePreBuilt('invalid');
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test missing repo
        try {
            ConfigValidator::validatePreBuilt(['match-pattern-linux' => '{name}-{arch}-{os}-{libc}-{libcver}.txz']);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test invalid repo type
        try {
            ConfigValidator::validatePreBuilt(['repo' => 123, 'match-pattern-linux' => '{name}-{arch}-{os}-{libc}-{libcver}.txz']);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test invalid prefer-stable type
        try {
            ConfigValidator::validatePreBuilt(['repo' => 'owner/repo', 'prefer-stable' => 'true', 'match-pattern-linux' => '{name}-{arch}-{os}-{libc}-{libcver}.txz']);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test no match patterns
        try {
            ConfigValidator::validatePreBuilt(['repo' => 'owner/repo']);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test invalid match pattern type
        try {
            ConfigValidator::validatePreBuilt(['repo' => 'owner/repo', 'match-pattern-linux' => 123]);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test missing {name} placeholder
        try {
            ConfigValidator::validatePreBuilt(['repo' => 'owner/repo', 'match-pattern-linux' => '{arch}-{os}-{libc}-{libcver}.txz']);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test missing {arch} placeholder
        try {
            ConfigValidator::validatePreBuilt(['repo' => 'owner/repo', 'match-pattern-linux' => '{name}-{os}-{libc}-{libcver}.txz']);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test missing {os} placeholder
        try {
            ConfigValidator::validatePreBuilt(['repo' => 'owner/repo', 'match-pattern-linux' => '{name}-{arch}-{libc}-{libcver}.txz']);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test linux pattern missing {libc} placeholder
        try {
            ConfigValidator::validatePreBuilt(['repo' => 'owner/repo', 'match-pattern-linux' => '{name}-{arch}-{os}-{libcver}.txz']);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Test linux pattern missing {libcver} placeholder
        try {
            ConfigValidator::validatePreBuilt(['repo' => 'owner/repo', 'match-pattern-linux' => '{name}-{arch}-{os}-{libc}.txz']);
            $this->fail('should throw ValidationException');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}
