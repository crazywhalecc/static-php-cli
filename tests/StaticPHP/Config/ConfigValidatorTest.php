<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Config;

use PHPUnit\Framework\TestCase;
use StaticPHP\Config\ConfigValidator;
use StaticPHP\Exception\ValidationException;

/**
 * @internal
 */
class ConfigValidatorTest extends TestCase
{
    public function testValidateAndLintArtifactsThrowsExceptionWhenDataIsNotArray(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('test.json is broken');

        $data = 'not an array';
        ConfigValidator::validateAndLintArtifacts('test.json', $data);
    }

    public function testValidateAndLintArtifactsWithCustomSource(): void
    {
        $data = [
            'test-artifact' => [
                'source' => 'custom',
            ],
        ];

        ConfigValidator::validateAndLintArtifacts('test.json', $data);

        $this->assertEquals('custom', $data['test-artifact']['source']);
    }

    public function testValidateAndLintArtifactsExpandsUrlString(): void
    {
        $data = [
            'test-artifact' => [
                'source' => 'https://example.com/file.tar.gz',
            ],
        ];

        ConfigValidator::validateAndLintArtifacts('test.json', $data);

        $this->assertIsArray($data['test-artifact']['source']);
        $this->assertEquals('url', $data['test-artifact']['source']['type']);
        $this->assertEquals('https://example.com/file.tar.gz', $data['test-artifact']['source']['url']);
    }

    public function testValidateAndLintArtifactsExpandsHttpUrlString(): void
    {
        $data = [
            'test-artifact' => [
                'source' => 'http://example.com/file.tar.gz',
            ],
        ];

        ConfigValidator::validateAndLintArtifacts('test.json', $data);

        $this->assertIsArray($data['test-artifact']['source']);
        $this->assertEquals('url', $data['test-artifact']['source']['type']);
        $this->assertEquals('http://example.com/file.tar.gz', $data['test-artifact']['source']['url']);
    }

    public function testValidateAndLintArtifactsWithSourceObject(): void
    {
        $data = [
            'test-artifact' => [
                'source' => [
                    'type' => 'git',
                    'url' => 'https://github.com/example/repo.git',
                    'rev' => 'main',
                ],
            ],
        ];

        ConfigValidator::validateAndLintArtifacts('test.json', $data);

        $this->assertIsArray($data['test-artifact']['source']);
        $this->assertEquals('git', $data['test-artifact']['source']['type']);
    }

    public function testValidateAndLintArtifactsWithBinaryCustom(): void
    {
        $data = [
            'test-artifact' => [
                'binary' => 'custom',
            ],
        ];

        ConfigValidator::validateAndLintArtifacts('test.json', $data);

        $this->assertIsArray($data['test-artifact']['binary']);
        $this->assertArrayHasKey('linux-x86_64', $data['test-artifact']['binary']);
        $this->assertEquals('custom', $data['test-artifact']['binary']['linux-x86_64']['type']);
    }

    public function testValidateAndLintArtifactsWithBinaryHosted(): void
    {
        $data = [
            'test-artifact' => [
                'binary' => 'hosted',
            ],
        ];

        ConfigValidator::validateAndLintArtifacts('test.json', $data);

        $this->assertIsArray($data['test-artifact']['binary']);
        $this->assertArrayHasKey('macos-aarch64', $data['test-artifact']['binary']);
        $this->assertEquals('hosted', $data['test-artifact']['binary']['macos-aarch64']['type']);
    }

    public function testValidateAndLintArtifactsWithBinaryPlatformObject(): void
    {
        $data = [
            'test-artifact' => [
                'binary' => [
                    'linux-x86_64' => [
                        'type' => 'url',
                        'url' => 'https://example.com/binary.tar.gz',
                    ],
                ],
            ],
        ];

        ConfigValidator::validateAndLintArtifacts('test.json', $data);

        $this->assertEquals('url', $data['test-artifact']['binary']['linux-x86_64']['type']);
    }

    public function testValidateAndLintArtifactsExpandsBinaryPlatformUrlString(): void
    {
        $data = [
            'test-artifact' => [
                'binary' => [
                    'linux-x86_64' => 'https://example.com/binary.tar.gz',
                ],
            ],
        ];

        ConfigValidator::validateAndLintArtifacts('test.json', $data);

        $this->assertIsArray($data['test-artifact']['binary']['linux-x86_64']);
        $this->assertEquals('url', $data['test-artifact']['binary']['linux-x86_64']['type']);
        $this->assertEquals('https://example.com/binary.tar.gz', $data['test-artifact']['binary']['linux-x86_64']['url']);
    }

    public function testValidateAndLintArtifactsWithSourceMirror(): void
    {
        $data = [
            'test-artifact' => [
                'source-mirror' => 'https://mirror.example.com/file.tar.gz',
            ],
        ];

        ConfigValidator::validateAndLintArtifacts('test.json', $data);

        $this->assertIsArray($data['test-artifact']['source-mirror']);
        $this->assertEquals('url', $data['test-artifact']['source-mirror']['type']);
    }

    public function testValidateAndLintPackagesThrowsExceptionWhenDataIsNotArray(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('pkg.json is broken');

        $data = 'not an array';
        ConfigValidator::validateAndLintPackages('pkg.json', $data);
    }

    public function testValidateAndLintPackagesThrowsExceptionWhenPackageIsNotAssocArray(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Package [test-pkg] in pkg.json is not a valid associative array');

        $data = [
            'test-pkg' => ['list', 'array'],
        ];
        ConfigValidator::validateAndLintPackages('pkg.json', $data);
    }

    public function testValidateAndLintPackagesThrowsExceptionWhenTypeMissing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Package [test-pkg] in pkg.json has invalid or missing 'type' field");

        $data = [
            'test-pkg' => [
                'depends' => [],
            ],
        ];
        ConfigValidator::validateAndLintPackages('pkg.json', $data);
    }

    public function testValidateAndLintPackagesThrowsExceptionWhenTypeInvalid(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Package [test-pkg] in pkg.json has invalid or missing 'type' field");

        $data = [
            'test-pkg' => [
                'type' => 'invalid-type',
            ],
        ];
        ConfigValidator::validateAndLintPackages('pkg.json', $data);
    }

    public function testValidateAndLintPackagesWithValidLibraryType(): void
    {
        $data = [
            'test-lib' => [
                'type' => 'library',
                'artifact' => 'test-artifact',
            ],
        ];

        ConfigValidator::validateAndLintPackages('pkg.json', $data);

        $this->assertEquals('library', $data['test-lib']['type']);
    }

    public function testValidateAndLintPackagesWithValidPhpExtensionType(): void
    {
        $data = [
            'test-ext' => [
                'type' => 'php-extension',
            ],
        ];

        ConfigValidator::validateAndLintPackages('pkg.json', $data);

        $this->assertEquals('php-extension', $data['test-ext']['type']);
    }

    public function testValidateAndLintPackagesWithValidTargetType(): void
    {
        $data = [
            'test-target' => [
                'type' => 'target',
                'artifact' => 'test-artifact',
            ],
        ];

        ConfigValidator::validateAndLintPackages('pkg.json', $data);

        $this->assertEquals('target', $data['test-target']['type']);
    }

    public function testValidateAndLintPackagesWithValidVirtualTargetType(): void
    {
        $data = [
            'test-virtual' => [
                'type' => 'virtual-target',
            ],
        ];

        ConfigValidator::validateAndLintPackages('pkg.json', $data);

        $this->assertEquals('virtual-target', $data['test-virtual']['type']);
    }

    public function testValidateAndLintPackagesThrowsExceptionWhenLibraryMissingArtifact(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Package [test-lib] in pkg.json of type 'library' must have an 'artifact' field");

        $data = [
            'test-lib' => [
                'type' => 'library',
            ],
        ];
        ConfigValidator::validateAndLintPackages('pkg.json', $data);
    }

    public function testValidateAndLintPackagesThrowsExceptionWhenTargetMissingArtifact(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Package [test-target] in pkg.json of type 'target' must have an 'artifact' field");

        $data = [
            'test-target' => [
                'type' => 'target',
            ],
        ];
        ConfigValidator::validateAndLintPackages('pkg.json', $data);
    }

    public function testValidateAndLintPackagesWithPhpExtensionFields(): void
    {
        $data = [
            'test-ext' => [
                'type' => 'php-extension',
                'php-extension' => [
                    'zend-extension' => false,
                    'build-shared' => true,
                ],
            ],
        ];

        ConfigValidator::validateAndLintPackages('pkg.json', $data);

        $this->assertIsArray($data['test-ext']['php-extension']);
    }

    public function testValidateAndLintPackagesThrowsExceptionWhenPhpExtensionIsNotObject(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Package test-ext [php-extension] must be an object');

        $data = [
            'test-ext' => [
                'type' => 'php-extension',
                'php-extension' => 'string',
            ],
        ];
        ConfigValidator::validateAndLintPackages('pkg.json', $data);
    }

    public function testValidateAndLintPackagesWithDependsField(): void
    {
        $data = [
            'test-pkg' => [
                'type' => 'library',
                'artifact' => 'test',
                'depends' => ['dep1', 'dep2'],
            ],
        ];

        ConfigValidator::validateAndLintPackages('pkg.json', $data);

        $this->assertIsArray($data['test-pkg']['depends']);
    }

    public function testValidateAndLintPackagesThrowsExceptionWhenDependsIsNotList(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Package test-pkg [depends] must be a list');

        $data = [
            'test-pkg' => [
                'type' => 'library',
                'artifact' => 'test',
                'depends' => 'not-a-list',
            ],
        ];
        ConfigValidator::validateAndLintPackages('pkg.json', $data);
    }

    public function testValidateAndLintPackagesWithSuffixFields(): void
    {
        $data = [
            'test-pkg' => [
                'type' => 'library',
                'artifact' => 'test',
                'depends@linux' => ['linux-dep'],
                'depends@windows' => ['windows-dep'],
                'headers@unix' => ['header.h'],
            ],
        ];

        ConfigValidator::validateAndLintPackages('pkg.json', $data);

        $this->assertIsArray($data['test-pkg']['depends@linux']);
    }

    public function testValidateAndLintPackagesThrowsExceptionForInvalidSuffixFieldType(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Package test-pkg [headers@linux] must be a list');

        $data = [
            'test-pkg' => [
                'type' => 'library',
                'artifact' => 'test',
                'headers@linux' => 'not-a-list',
            ],
        ];
        ConfigValidator::validateAndLintPackages('pkg.json', $data);
    }

    public function testValidateAndLintPackagesThrowsExceptionForUnknownField(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('package [test-pkg] has invalid field [unknown-field]');

        $data = [
            'test-pkg' => [
                'type' => 'library',
                'artifact' => 'test',
                'unknown-field' => 'value',
            ],
        ];
        ConfigValidator::validateAndLintPackages('pkg.json', $data);
    }

    public function testValidateAndLintPackagesThrowsExceptionForUnknownPhpExtensionField(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('php-extension [test-ext] has invalid field [unknown]');

        $data = [
            'test-ext' => [
                'type' => 'php-extension',
                'php-extension' => [
                    'unknown' => 'value',
                ],
            ],
        ];
        ConfigValidator::validateAndLintPackages('pkg.json', $data);
    }

    public function testValidatePlatformStringWithValidPlatforms(): void
    {
        ConfigValidator::validatePlatformString('linux-x86_64');
        ConfigValidator::validatePlatformString('linux-aarch64');
        ConfigValidator::validatePlatformString('windows-x86_64');
        ConfigValidator::validatePlatformString('windows-aarch64');
        ConfigValidator::validatePlatformString('macos-x86_64');
        ConfigValidator::validatePlatformString('macos-aarch64');

        $this->assertTrue(true); // If no exception thrown, test passes
    }

    public function testValidatePlatformStringThrowsExceptionForInvalidFormat(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid platform format 'invalid', expected format 'os-arch'");

        ConfigValidator::validatePlatformString('invalid');
    }

    public function testValidatePlatformStringThrowsExceptionForTooManyParts(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid platform format 'linux-x86_64-extra', expected format 'os-arch'");

        ConfigValidator::validatePlatformString('linux-x86_64-extra');
    }

    public function testValidatePlatformStringThrowsExceptionForInvalidOS(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid platform OS 'bsd' in platform 'bsd-x86_64'");

        ConfigValidator::validatePlatformString('bsd-x86_64');
    }

    public function testValidatePlatformStringThrowsExceptionForInvalidArch(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid platform architecture 'arm' in platform 'linux-arm'");

        ConfigValidator::validatePlatformString('linux-arm');
    }

    public function testArtifactTypeFieldsConstant(): void
    {
        $this->assertArrayHasKey('filelist', ConfigValidator::ARTIFACT_TYPE_FIELDS);
        $this->assertArrayHasKey('git', ConfigValidator::ARTIFACT_TYPE_FIELDS);
        $this->assertArrayHasKey('ghtagtar', ConfigValidator::ARTIFACT_TYPE_FIELDS);
        $this->assertArrayHasKey('url', ConfigValidator::ARTIFACT_TYPE_FIELDS);
        $this->assertArrayHasKey('custom', ConfigValidator::ARTIFACT_TYPE_FIELDS);
    }

    public function testValidateAndLintArtifactsThrowsExceptionForInvalidArtifactType(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Artifact source object has unknown type 'invalid-type'");

        $data = [
            'test-artifact' => [
                'source' => [
                    'type' => 'invalid-type',
                ],
            ],
        ];
        ConfigValidator::validateAndLintArtifacts('test.json', $data);
    }

    public function testValidateAndLintArtifactsThrowsExceptionForMissingRequiredField(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Artifact source object of type 'git' must have required field 'url'");

        $data = [
            'test-artifact' => [
                'source' => [
                    'type' => 'git',
                    'rev' => 'main',
                    // missing 'url'
                ],
            ],
        ];
        ConfigValidator::validateAndLintArtifacts('test.json', $data);
    }

    public function testValidateAndLintArtifactsThrowsExceptionForMissingTypeInSource(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Artifact source object must have a valid 'type' field");

        $data = [
            'test-artifact' => [
                'source' => [
                    'url' => 'https://example.com',
                ],
            ],
        ];
        ConfigValidator::validateAndLintArtifacts('test.json', $data);
    }

    public function testValidateAndLintArtifactsWithAllArtifactTypes(): void
    {
        $data = [
            'filelist-artifact' => [
                'source' => [
                    'type' => 'filelist',
                    'url' => 'https://example.com/list',
                    'regex' => '/pattern/',
                ],
            ],
            'git-artifact' => [
                'source' => [
                    'type' => 'git',
                    'url' => 'https://github.com/example/repo.git',
                    'rev' => 'main',
                ],
            ],
            'ghtagtar-artifact' => [
                'source' => [
                    'type' => 'ghtagtar',
                    'repo' => 'example/repo',
                ],
            ],
            'url-artifact' => [
                'source' => [
                    'type' => 'url',
                    'url' => 'https://example.com/file.tar.gz',
                ],
            ],
            'custom-artifact' => [
                'source' => [
                    'type' => 'custom',
                ],
            ],
        ];

        ConfigValidator::validateAndLintArtifacts('test.json', $data);

        $this->assertIsArray($data);
    }

    public function testValidateAndLintPackagesWithAllFieldTypes(): void
    {
        $data = [
            'test-pkg' => [
                'type' => 'library',
                'artifact' => 'test-artifact',
                'depends' => ['dep1'],
                'suggests' => ['sug1'],
                'license' => [
                    'type' => 'file',
                    'path' => 'LICENSE',
                ],
                'lang' => 'c',
                'frameworks' => ['framework1'],
                'headers' => ['header.h'],
                'static-libs' => ['lib.a'],
                'pkg-configs' => ['pkg.pc'],
                'static-bins' => ['bin'],
            ],
        ];

        ConfigValidator::validateAndLintPackages('pkg.json', $data);

        $this->assertEquals('library', $data['test-pkg']['type']);
    }

    public function testValidateAndLintPackagesThrowsExceptionForWrongTypeString(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Package test-pkg [artifact] must be string');

        $data = [
            'test-pkg' => [
                'type' => 'library',
                'artifact' => ['not', 'a', 'string'],
            ],
        ];
        ConfigValidator::validateAndLintPackages('pkg.json', $data);
    }

    public function testValidateAndLintPackagesThrowsExceptionForWrongTypeBool(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Package test-ext [zend-extension] must be boolean');

        $data = [
            'test-ext' => [
                'type' => 'php-extension',
                'php-extension' => [
                    'zend-extension' => 'not-a-bool',
                ],
            ],
        ];
        ConfigValidator::validateAndLintPackages('pkg.json', $data);
    }

    public function testValidateAndLintPackagesThrowsExceptionForWrongTypeAssocArray(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Package test-pkg [support] must be an object');

        $data = [
            'test-pkg' => [
                'type' => 'php-extension',
                'php-extension' => [
                    'support' => 'not-an-object',
                ],
            ],
        ];
        ConfigValidator::validateAndLintPackages('pkg.json', $data);
    }
}
