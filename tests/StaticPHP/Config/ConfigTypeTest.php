<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Config;

use PHPUnit\Framework\TestCase;
use StaticPHP\Config\ConfigType;

/**
 * @internal
 */
class ConfigTypeTest extends TestCase
{
    public function testConstantValues(): void
    {
        $this->assertEquals('list_array', ConfigType::LIST_ARRAY);
        $this->assertEquals('assoc_array', ConfigType::ASSOC_ARRAY);
        $this->assertEquals('string', ConfigType::STRING);
        $this->assertEquals('bool', ConfigType::BOOL);
    }

    public function testPackageTypesConstant(): void
    {
        $expectedTypes = [
            'library',
            'php-extension',
            'target',
            'virtual-target',
        ];

        $this->assertEquals($expectedTypes, ConfigType::PACKAGE_TYPES);
    }

    public function testValidateLicenseFieldWithValidFileType(): void
    {
        $license = [
            'type' => 'file',
            'path' => 'LICENSE',
        ];

        $this->assertTrue(ConfigType::validateLicenseField($license));
    }

    public function testValidateLicenseFieldWithValidFileTypeArrayPath(): void
    {
        $license = [
            'type' => 'file',
            'path' => ['LICENSE', 'COPYING'],
        ];

        $this->assertTrue(ConfigType::validateLicenseField($license));
    }

    public function testValidateLicenseFieldWithValidTextType(): void
    {
        $license = [
            'type' => 'text',
            'text' => 'MIT License',
        ];

        $this->assertTrue(ConfigType::validateLicenseField($license));
    }

    public function testValidateLicenseFieldWithListOfLicenses(): void
    {
        $licenses = [
            [
                'type' => 'file',
                'path' => 'LICENSE',
            ],
            [
                'type' => 'text',
                'text' => 'MIT',
            ],
        ];

        $this->assertTrue(ConfigType::validateLicenseField($licenses));
    }

    public function testValidateLicenseFieldWithEmptyList(): void
    {
        $licenses = [];

        $this->assertTrue(ConfigType::validateLicenseField($licenses));
    }

    public function testValidateLicenseFieldReturnsFalseWhenNotAssocArray(): void
    {
        $this->assertFalse(ConfigType::validateLicenseField('string'));
        $this->assertFalse(ConfigType::validateLicenseField(123));
        $this->assertFalse(ConfigType::validateLicenseField(true));
    }

    public function testValidateLicenseFieldReturnsFalseWhenMissingType(): void
    {
        $license = [
            'path' => 'LICENSE',
        ];

        $this->assertFalse(ConfigType::validateLicenseField($license));
    }

    public function testValidateLicenseFieldReturnsFalseWithInvalidType(): void
    {
        $license = [
            'type' => 'invalid',
            'data' => 'something',
        ];

        $this->assertFalse(ConfigType::validateLicenseField($license));
    }

    public function testValidateLicenseFieldReturnsFalseWhenFileTypeMissingPath(): void
    {
        $license = [
            'type' => 'file',
        ];

        $this->assertFalse(ConfigType::validateLicenseField($license));
    }

    public function testValidateLicenseFieldReturnsFalseWhenFileTypePathIsInvalid(): void
    {
        $license = [
            'type' => 'file',
            'path' => 123,
        ];

        $this->assertFalse(ConfigType::validateLicenseField($license));
    }

    public function testValidateLicenseFieldReturnsFalseWhenTextTypeMissingText(): void
    {
        $license = [
            'type' => 'text',
        ];

        $this->assertFalse(ConfigType::validateLicenseField($license));
    }

    public function testValidateLicenseFieldReturnsFalseWhenTextTypeTextIsNotString(): void
    {
        $license = [
            'type' => 'text',
            'text' => ['array'],
        ];

        $this->assertFalse(ConfigType::validateLicenseField($license));
    }

    public function testValidateLicenseFieldWithListContainingInvalidItem(): void
    {
        $licenses = [
            [
                'type' => 'file',
                'path' => 'LICENSE',
            ],
            [
                'type' => 'text',
                // missing 'text' field
            ],
        ];

        $this->assertFalse(ConfigType::validateLicenseField($licenses));
    }

    public function testValidateLicenseFieldWithNestedListsOfLicenses(): void
    {
        $licenses = [
            [
                [
                    'type' => 'file',
                    'path' => 'LICENSE',
                ],
            ],
        ];

        $this->assertTrue(ConfigType::validateLicenseField($licenses));
    }

    public function testValidateLicenseFieldWithNestedListContainingInvalidItem(): void
    {
        $licenses = [
            [
                [
                    'type' => 'file',
                    'path' => 'LICENSE',
                ],
                'invalid-string-item',
            ],
        ];

        $this->assertFalse(ConfigType::validateLicenseField($licenses));
    }
}
