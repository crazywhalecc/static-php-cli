<?php

declare(strict_types=1);

namespace StaticPHP\Config;

enum ConfigType
{
    public const string LIST_ARRAY = 'list_array';

    public const string ASSOC_ARRAY = 'assoc_array';

    public const string STRING = 'string';

    public const string BOOL = 'bool';

    public const array PACKAGE_TYPES = [
        'library',
        'php-extension',
        'target',
        'virtual-target',
    ];

    public static function validateLicenseField(mixed $value): bool
    {
        if (is_list_array($value)) {
            foreach ($value as $item) {
                if (!self::validateLicenseField($item)) {
                    return false;
                }
            }
            return true;
        }
        if (!is_assoc_array($value)) {
            return false;
        }
        if (!isset($value['type'])) {
            return false;
        }
        return match ($value['type']) {
            'file' => match (true) {
                !isset($value['path']), !is_string($value['path']) && !is_array($value['path']) => false,
                default => true,
            },
            'text' => match (true) {
                !isset($value['text']), !is_string($value['text']) => false,
                default => true,
            },
            default => false,
        };
    }
}
