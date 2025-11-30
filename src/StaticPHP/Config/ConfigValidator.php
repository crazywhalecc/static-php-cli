<?php

declare(strict_types=1);

namespace StaticPHP\Config;

use StaticPHP\Exception\ValidationException;

class ConfigValidator
{
    /**
     * Global field type definitions
     * Maps field names to their expected types and validation rules
     * Note: This only includes fields used in config files (source.json, lib.json, ext.json, pkg.json, pre-built.json)
     */
    public const array PACKAGE_FIELD_TYPES = [
        // package fields
        'type' => ConfigType::STRING,
        'depends' => ConfigType::LIST_ARRAY, // @
        'suggests' => ConfigType::LIST_ARRAY, // @
        'artifact' => ConfigType::STRING,
        'license' => [ConfigType::class, 'validateLicenseField'],
        'lang' => ConfigType::STRING,
        'frameworks' => ConfigType::LIST_ARRAY, // @

        // php-extension type fields
        'php-extension' => ConfigType::ASSOC_ARRAY,
        'zend-extension' => ConfigType::BOOL,
        'support' => ConfigType::ASSOC_ARRAY,
        'arg-type' => ConfigType::STRING,
        'build-shared' => ConfigType::BOOL,
        'build-static' => ConfigType::BOOL,
        'build-with-php' => ConfigType::BOOL,
        'notes' => ConfigType::BOOL,

        // library and target fields
        'headers' => ConfigType::LIST_ARRAY, // @
        'static-libs' => ConfigType::LIST_ARRAY, // @
        'pkg-configs' => ConfigType::LIST_ARRAY,
        'static-bins' => ConfigType::LIST_ARRAY, // @
    ];

    public const array PACKAGE_FIELDS = [
        'type' => true,
        'depends' => false, // @
        'suggests' => false, // @
        'artifact' => false,
        'license' => false,
        'lang' => false,
        'frameworks' => false, // @

        // php-extension type fields
        'php-extension' => false,

        // library and target fields
        'headers' => false, // @
        'static-libs' => false, // @
        'pkg-configs' => false,
        'static-bins' => false, // @
    ];

    public const array SUFFIX_ALLOWED_FIELDS = [
        'depends',
        'suggests',
        'headers',
        'static-libs',
        'static-bins',
    ];

    public const array PHP_EXTENSION_FIELDS = [
        'zend-extension' => false,
        'support' => false,
        'arg-type' => false, // @
        'build-shared' => false,
        'build-static' => false,
        'build-with-php' => false,
        'notes' => false,
    ];

    public const array ARTIFACT_TYPE_FIELDS = [ // [required_fields, optional_fields]
        'filelist' => [['url', 'regex'], ['extract']],
        'git' => [['url', 'rev'], ['extract', 'submodules']],
        'ghtagtar' => [['repo'], ['extract', 'prefer-stable', 'match']],
        'ghtar' => [['repo'], ['extract', 'prefer-stable', 'match']],
        'ghrel' => [['repo', 'match'], ['extract', 'prefer-stable']],
        'url' => [['url'], ['filename', 'extract', 'version']],
        'bitbuckettag' => [['repo'], ['extract']],
        'local' => [['dirname'], ['extract']],
        'pie' => [['repo'], ['extract']],
        'php-release' => [[], ['extract']],
        'custom' => [[], ['func']],
    ];

    /**
     * Validate and standardize artifacts configuration data.
     *
     * @param string $config_file_name Name of the configuration file (for error messages)
     * @param mixed  $data             The configuration data to validate
     */
    public static function validateAndLintArtifacts(string $config_file_name, mixed &$data): void
    {
        if (!is_array($data)) {
            throw new ValidationException("{$config_file_name} is broken");
        }
        foreach ($data as $name => $artifact) {
            foreach ($artifact as $k => $v) {
                // check source field
                if ($k === 'source' || $k === 'source-mirror') {
                    // source === custom is allowed
                    if ($v === 'custom') {
                        continue;
                    }
                    // expand string to url type (start with http:// or https://)
                    if (is_string($v) && (str_starts_with($v, 'http://') || str_starts_with($v, 'https://'))) {
                        $data[$name][$k] = [
                            'type' => 'url',
                            'url' => $v,
                        ];
                        continue;
                    }
                    // source: object with type field
                    if (is_assoc_array($v)) {
                        self::validateArtifactObjectField($name, $v);
                    }
                    continue;
                }
                // check binary field
                if ($k === 'binary') {
                    // binary === custom is allowed
                    if ($v === 'custom') {
                        $data[$name][$k] = [
                            'linux-x86_64' => ['type' => 'custom'],
                            'linux-aarch64' => ['type' => 'custom'],
                            'windows-x86_64' => ['type' => 'custom'],
                            'macos-x86_64' => ['type' => 'custom'],
                            'macos-aarch64' => ['type' => 'custom'],
                        ];
                        continue;
                    }
                    // TODO: expand hosted to static-php hosted download urls
                    if ($v === 'hosted') {
                        continue;
                    }
                    if (is_assoc_array($v)) {
                        foreach ($v as $platform => $v_obj) {
                            self::validatePlatformString($platform);
                            // expand string to url type (start with http:// or https://)
                            if (is_string($v_obj) && (str_starts_with($v_obj, 'http://') || str_starts_with($v_obj, 'https://'))) {
                                $data[$name][$k][$platform] = [
                                    'type' => 'url',
                                    'url' => $v_obj,
                                ];
                                continue;
                            }
                            // binary: object with type field
                            if (is_assoc_array($v_obj)) {
                                self::validateArtifactObjectField("{$name}::{$platform}", $v_obj);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Validate packages configuration data.
     *
     * @param string $config_file_name Name of the configuration file (for error messages)
     * @param mixed  $data             The configuration data to validate
     */
    public static function validateAndLintPackages(string $config_file_name, mixed &$data): void
    {
        if (!is_array($data)) {
            throw new ValidationException("{$config_file_name} is broken");
        }
        foreach ($data as $name => $pkg) {
            if (!is_assoc_array($pkg)) {
                throw new ValidationException("Package [{$name}] in {$config_file_name} is not a valid associative array");
            }
            // check if package has valid type
            if (!isset($pkg['type']) || !in_array($pkg['type'], ConfigType::PACKAGE_TYPES)) {
                throw new ValidationException("Package [{$name}] in {$config_file_name} has invalid or missing 'type' field");
            }

            // validate basic fields using unified method
            self::validatePackageFields($name, $pkg);

            // validate list of suffix-allowed fields
            $suffixes = ['', '@windows', '@unix', '@macos', '@linux'];
            $fields = self::SUFFIX_ALLOWED_FIELDS;
            self::validateSuffixAllowedFields($name, $pkg, $fields, $suffixes);

            // check if "library|target" package has artifact field for target and library types
            if (in_array($pkg['type'], ['target', 'library']) && !isset($pkg['artifact'])) {
                throw new ValidationException("Package [{$name}] in {$config_file_name} of type '{$pkg['type']}' must have an 'artifact' field");
            }

            // check if "php-extension" package has php-extension specific fields and validate inside
            if ($pkg['type'] === 'php-extension') {
                self::validatePhpExtensionFields($name, $pkg);
            }

            // check for unknown fields
            self::validateNoInvalidFields('package', $name, $pkg, array_keys(self::PACKAGE_FIELD_TYPES));
        }
    }

    /**
     * Validate platform string format.
     *
     * @param string $platform Platform string, like windows-x86_64
     */
    public static function validatePlatformString(string $platform): void
    {
        $valid_platforms = ['windows', 'linux', 'macos'];
        $valid_arch = ['x86_64', 'aarch64'];
        $parts = explode('-', $platform);
        if (count($parts) !== 2) {
            throw new ValidationException("Invalid platform format '{$platform}', expected format 'os-arch'");
        }
        [$os, $arch] = $parts;
        if (!in_array($os, $valid_platforms)) {
            throw new ValidationException("Invalid platform OS '{$os}' in platform '{$platform}'");
        }
        if (!in_array($arch, $valid_arch)) {
            throw new ValidationException("Invalid platform architecture '{$arch}' in platform '{$platform}'");
        }
    }

    /**
     * Validate an artifact download object field.
     *
     * @param string $item_name Artifact name (for error messages)
     * @param array  $data      Artifact source object data
     */
    private static function validateArtifactObjectField(string $item_name, array $data): void
    {
        if (!isset($data['type']) || !is_string($data['type'])) {
            throw new ValidationException("Artifact source object must have a valid 'type' field");
        }
        $type = $data['type'];
        if (!isset(self::ARTIFACT_TYPE_FIELDS[$type])) {
            throw new ValidationException("Artifact source object has unknown type '{$type}'");
        }
        [$required_fields, $optional_fields] = self::ARTIFACT_TYPE_FIELDS[$type];
        // check required fields
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                throw new ValidationException("Artifact source object of type '{$type}' must have required field '{$field}'");
            }
        }
        // check for unknown fields
        $allowed_fields = array_merge(['type'], $required_fields, $optional_fields);
        self::validateNoInvalidFields('artifact object', $item_name, $data, $allowed_fields);
    }

    /**
     * Unified method to validate config fields based on field definitions
     *
     * @param string $package_name Package name
     * @param mixed  $pkg          The package configuration array
     */
    private static function validatePackageFields(string $package_name, mixed $pkg): void
    {
        foreach (self::PACKAGE_FIELDS as $field => $required) {
            if ($required && !isset($pkg[$field])) {
                throw new ValidationException("Package {$package_name} must have [{$field}] field");
            }

            if (isset($pkg[$field])) {
                self::validatePackageFieldType($field, $pkg[$field], $package_name);
            }
        }
    }

    /**
     * Validate a field based on its global type definition
     *
     * @param string $field        Field name
     * @param mixed  $value        Field value
     * @param string $package_name Package name (for error messages)
     */
    private static function validatePackageFieldType(string $field, mixed $value, string $package_name): void
    {
        // Check if field exists in FIELD_TYPES
        if (!isset(self::PACKAGE_FIELD_TYPES[$field])) {
            // Try to strip suffix and check base field name
            $suffixes = ['@windows', '@unix', '@macos', '@linux'];
            $base_field = $field;
            foreach ($suffixes as $suffix) {
                if (str_ends_with($field, $suffix)) {
                    $base_field = substr($field, 0, -strlen($suffix));
                    break;
                }
            }

            if (!isset(self::PACKAGE_FIELD_TYPES[$base_field])) {
                // Unknown field is not allowed - strict validation
                throw new ValidationException("Package {$package_name} has unknown field [{$field}]");
            }

            // Use base field type for validation
            $expected_type = self::PACKAGE_FIELD_TYPES[$base_field];
        } else {
            $expected_type = self::PACKAGE_FIELD_TYPES[$field];
        }

        match ($expected_type) {
            ConfigType::STRING => is_string($value) ?: throw new ValidationException("Package {$package_name} [{$field}] must be string"),
            ConfigType::BOOL => is_bool($value) ?: throw new ValidationException("Package {$package_name} [{$field}] must be boolean"),
            ConfigType::LIST_ARRAY => is_list_array($value) ?: throw new ValidationException("Package {$package_name} [{$field}] must be a list"),
            ConfigType::ASSOC_ARRAY => is_assoc_array($value) ?: throw new ValidationException("Package {$package_name} [{$field}] must be an object"),
            default => $expected_type($value) ?: throw new ValidationException("Package {$package_name} [{$field}] has invalid type specification"),
        };
    }

    /**
     * Validate that fields with suffixes are list arrays
     */
    private static function validateSuffixAllowedFields(int|string $name, mixed $item, array $fields, array $suffixes): void
    {
        foreach ($fields as $field) {
            foreach ($suffixes as $suffix) {
                $key = $field . $suffix;
                if (isset($item[$key])) {
                    self::validatePackageFieldType($key, $item[$key], $name);
                }
            }
        }
    }

    /**
     * Validate php-extension specific fields for php-extension package
     */
    private static function validatePhpExtensionFields(int|string $name, mixed $pkg): void
    {
        if (!isset($pkg['php-extension'])) {
            return;
        }
        if (!is_assoc_array($pkg['php-extension'])) {
            throw new ValidationException("Package {$name} [php-extension] must be an object");
        }
        foreach (self::PHP_EXTENSION_FIELDS as $field => $required) {
            if (isset($pkg['php-extension'][$field])) {
                self::validatePackageFieldType($field, $pkg['php-extension'][$field], $name);
            }
        }
        // check for unknown fields in php-extension
        self::validateNoInvalidFields('php-extension', $name, $pkg['php-extension'], array_keys(self::PHP_EXTENSION_FIELDS));
    }

    private static function validateNoInvalidFields(string $config_type, int|string $item_name, mixed $item_content, array $allowed_fields): void
    {
        foreach ($item_content as $k => $v) {
            // remove suffixes for checking
            $base_k = $k;
            $suffixes = ['@windows', '@unix', '@macos', '@linux'];
            foreach ($suffixes as $suffix) {
                if (str_ends_with($k, $suffix)) {
                    $base_k = substr($k, 0, -strlen($suffix));
                    break;
                }
            }
            if (!in_array($base_k, $allowed_fields)) {
                throw new ValidationException("{$config_type} [{$item_name}] has invalid field [{$base_k}]");
            }
        }
    }
}
