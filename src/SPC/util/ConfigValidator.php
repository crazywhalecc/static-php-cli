<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\ValidationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ConfigValidator
{
    /**
     * Global field type definitions
     * Maps field names to their expected types and validation rules
     * Note: This only includes fields used in config files (source.json, lib.json, ext.json, pkg.json, pre-built.json)
     */
    private const array FIELD_TYPES = [
        // String fields
        'url' => 'string',      // url
        'regex' => 'string',    // regex pattern
        'rev' => 'string',      // revision/branch
        'repo' => 'string',     // repository name
        'match' => 'string',    // match pattern (aaa*bbb)
        'filename' => 'string', // filename
        'path' => 'string',     // copy path
        'extract' => 'string',  // copy path (alias of path)
        'dirname' => 'string',  // directory name for local source
        'source' => 'string',   // the source name that this item uses
        'match-pattern-linux' => 'string',   // pre-built match pattern for linux
        'match-pattern-macos' => 'string',   // pre-built match pattern for macos
        'match-pattern-windows' => 'string', // pre-built match pattern for windows

        // Boolean fields
        'prefer-stable' => 'bool',      // prefer stable releases
        'provide-pre-built' => 'bool',  // provide pre-built binaries
        'notes' => 'bool',              // whether to show notes in docs
        'cpp-library' => 'bool',        // whether this is a C++ library
        'cpp-extension' => 'bool',      // whether this is a C++ extension
        'build-with-php' => 'bool',     // whether if this extension can be built to shared with PHP source together
        'zend-extension' => 'bool',     // whether this is a zend extension
        'unix-only' => 'bool',          // whether this extension is only for unix-like systems

        // Array fields
        'submodules' => 'array',    // git submodules list (for git source type)
        'lib-depends' => 'list',
        'lib-suggests' => 'list',
        'ext-depends' => 'list',
        'ext-suggests' => 'list',
        'static-libs' => 'list',
        'pkg-configs' => 'list',    // required pkg-config files without suffix (e.g. [libwebp])
        'headers' => 'list',        // required header files
        'bin' => 'list',            // required binary files
        'frameworks' => 'list',     // shared library frameworks (macOS)

        // Object/assoc array fields
        'support' => 'object',          // extension OS support docs
        'extract-files' => 'object',    // pkg.json extract files mapping with match pattern
        'alt' => 'object|bool',         // alternative source/package
        'license' => 'object|array',    // license information
        'target' => 'array',            // extension build targets (default: [static], alternate: [shared] or both)

        // Special/mixed fields
        'func' => 'callable',           // custom download function for custom source/package type
        'type' => 'string',             // type field (validated separately)
    ];

    /**
     * Source/Package download type validation rules
     * Maps type names to [required_props, optional_props]
     */
    private const array SOURCE_TYPE_FIELDS = [
        'filelist' => [['url', 'regex'], []],
        'git' => [['url', 'rev'], ['path', 'extract', 'submodules']],
        'ghtagtar' => [['repo'], ['path', 'extract', 'prefer-stable', 'match']],
        'ghtar' => [['repo'], ['path', 'extract', 'prefer-stable', 'match']],
        'ghrel' => [['repo', 'match'], ['path', 'extract', 'prefer-stable']],
        'url' => [['url'], ['filename', 'path', 'extract']],
        'bitbuckettag' => [['repo'], ['path', 'extract']],
        'local' => [['dirname'], ['path', 'extract']],
        'pie' => [['repo'], ['path']],
        'custom' => [[], ['func']],
    ];

    /**
     * Source.json specific fields [field_name => required]
     * Note: 'type' is validated separately in validateSourceTypeConfig
     * Field types are defined in FIELD_TYPES constant
     */
    private const array SOURCE_FIELDS = [
        'type' => true,                 // source type (must be SOURCE_TYPE_FIELDS key)
        'provide-pre-built' => false,   // whether to provide pre-built binaries
        'alt' => false,                 // alternative source configuration
        'license' => false,             // license information for source
        // ... other fields are validated based on source type
    ];

    /**
     * Lib.json specific fields [field_name => required]
     * Field types are defined in FIELD_TYPES constant
     */
    private const array LIB_FIELDS = [
        'type' => false,        // lib type (lib/package/target/root)
        'source' => false,      // the source name that this lib uses
        'lib-depends' => false, // required libraries
        'lib-suggests' => false, // suggested libraries
        'static-libs' => false, // Generated static libraries
        'pkg-configs' => false, // Generated pkg-config files
        'cpp-library' => false, // whether this is a C++ library
        'headers' => false,     // Generated header files
        'bin' => false,         // Generated binary files
        'frameworks' => false,  // Used shared library frameworks (macOS)
    ];

    /**
     * Ext.json specific fields [field_name => required]
     * Field types are defined in FIELD_TYPES constant
     */
    private const array EXT_FIELDS = [
        'type' => true,             // extension type (builtin/external/addon/wip)
        'source' => false,          // the source name that this extension uses
        'support' => false,         // extension OS support docs
        'notes' => false,           // whether to show notes in docs
        'cpp-extension' => false,   // whether this is a C++ extension
        'build-with-php' => false,  // whether if this extension can be built to shared with PHP source together
        'target' => false,          // extension build targets (default: [static], alternate: [shared] or both)
        'lib-depends' => false,
        'lib-suggests' => false,
        'ext-depends' => false,
        'ext-suggests' => false,
        'frameworks' => false,
        'zend-extension' => false,  // whether this is a zend extension
        'unix-only' => false,       // whether this extension is only for unix-like systems
    ];

    /**
     * Pkg.json specific fields [field_name => required]
     * Field types are defined in FIELD_TYPES constant
     */
    private const array PKG_FIELDS = [
        'type' => true,             // package type (same as source type)
        'extract-files' => false,   // files to extract mapping (source pattern => target path)
    ];

    /**
     * Pre-built.json specific fields [field_name => required]
     * Field types are defined in FIELD_TYPES constant
     */
    private const array PRE_BUILT_FIELDS = [
        'repo' => true,                     // repository name for pre-built binaries
        'prefer-stable' => false,           // prefer stable releases
        'match-pattern-linux' => false,     // pre-built match pattern for linux
        'match-pattern-macos' => false,     // pre-built match pattern for macos
        'match-pattern-windows' => false,   // pre-built match pattern for windows
    ];

    /**
     * Validate source.json
     *
     * @param array $data source.json data array
     */
    public static function validateSource(array $data): void
    {
        foreach ($data as $name => $src) {
            // Validate basic source type configuration
            self::validateSourceTypeConfig($src, $name, 'source');

            // Validate all source-specific fields using unified method
            self::validateConfigFields($src, $name, 'source', self::SOURCE_FIELDS);

            // Check for unknown fields
            self::validateAllowedFields($src, $name, 'source', self::SOURCE_FIELDS);

            // check if alt is valid
            if (isset($src['alt']) && is_assoc_array($src['alt'])) {
                // validate alt source recursively
                self::validateSource([$name . '_alt' => $src['alt']]);
            }

            // check if license is valid
            if (isset($src['license'])) {
                if (is_assoc_array($src['license'])) {
                    self::checkSingleLicense($src['license'], $name);
                } elseif (is_list_array($src['license'])) {
                    foreach ($src['license'] as $license) {
                        if (!is_assoc_array($license)) {
                            throw new ValidationException("source {$name} license must be an object or array");
                        }
                        self::checkSingleLicense($license, $name);
                    }
                }
            }
        }
    }

    public static function validateLibs(mixed $data, array $source_data = []): void
    {
        // check if it is an array
        if (!is_array($data)) {
            throw new ValidationException('lib.json is broken');
        }

        foreach ($data as $name => $lib) {
            if (!is_assoc_array($lib)) {
                throw new ValidationException("lib {$name} is not an object");
            }
            // check if lib has valid type
            if (!in_array($lib['type'] ?? 'lib', ['lib', 'package', 'target', 'root'])) {
                throw new ValidationException("lib {$name} type is invalid");
            }
            // check if lib and package has source
            if (in_array($lib['type'] ?? 'lib', ['lib', 'package']) && !isset($lib['source'])) {
                throw new ValidationException("lib {$name} does not assign any source");
            }
            // check if source is valid
            if (isset($lib['source']) && !empty($source_data) && !isset($source_data[$lib['source']])) {
                throw new ValidationException("lib {$name} assigns an invalid source: {$lib['source']}");
            }

            // Validate basic fields using unified method
            self::validateConfigFields($lib, $name, 'lib', self::LIB_FIELDS);

            // Validate list array fields with suffixes
            $suffixes = ['', '-windows', '-unix', '-macos', '-linux'];
            $fields = ['lib-depends', 'lib-suggests', 'static-libs', 'pkg-configs', 'headers', 'bin'];
            self::validateListArrayFields($lib, $name, 'lib', $fields, $suffixes);

            // Validate frameworks (special case without suffix)
            if (isset($lib['frameworks'])) {
                self::validateFieldType('frameworks', $lib['frameworks'], $name, 'lib');
            }

            // Check for unknown fields
            self::validateAllowedFields($lib, $name, 'lib', self::LIB_FIELDS);
        }
    }

    public static function validateExts(mixed $data): void
    {
        if (!is_array($data)) {
            throw new ValidationException('ext.json is broken');
        }

        foreach ($data as $name => $ext) {
            if (!is_assoc_array($ext)) {
                throw new ValidationException("ext {$name} is not an object");
            }

            if (!in_array($ext['type'] ?? '', ['builtin', 'external', 'addon', 'wip'])) {
                throw new ValidationException("ext {$name} type is invalid");
            }

            // Check source field requirement
            if (($ext['type'] ?? '') === 'external' && !isset($ext['source'])) {
                throw new ValidationException("ext {$name} does not assign any source");
            }

            // Validate basic fields using unified method
            self::validateConfigFields($ext, $name, 'ext', self::EXT_FIELDS);

            // Validate list array fields with suffixes
            $suffixes = ['', '-windows', '-unix', '-macos', '-linux'];
            $fields = ['lib-depends', 'lib-suggests', 'ext-depends', 'ext-suggests'];
            self::validateListArrayFields($ext, $name, 'ext', $fields, $suffixes);

            // Validate arg-type fields
            self::validateArgTypeFields($ext, $name, $suffixes);

            // Check for unknown fields
            self::validateAllowedFields($ext, $name, 'ext', self::EXT_FIELDS);
        }
    }

    public static function validatePkgs(mixed $data): void
    {
        if (!is_array($data)) {
            throw new ValidationException('pkg.json is broken');
        }
        // check each package
        foreach ($data as $name => $pkg) {
            // check if pkg is an assoc array
            if (!is_assoc_array($pkg)) {
                throw new ValidationException("pkg {$name} is not an object");
            }

            // Validate basic source type configuration (reuse from source validation)
            self::validateSourceTypeConfig($pkg, $name, 'pkg');

            // Validate all pkg-specific fields using unified method
            self::validateConfigFields($pkg, $name, 'pkg', self::PKG_FIELDS);

            // Validate extract-files content (object validation is done by validateFieldType)
            if (isset($pkg['extract-files'])) {
                // check each extract file mapping
                foreach ($pkg['extract-files'] as $source => $target) {
                    if (!is_string($source) || !is_string($target)) {
                        throw new ValidationException("pkg {$name} extract-files mapping must be string to string");
                    }
                }
            }

            // Check for unknown fields
            self::validateAllowedFields($pkg, $name, 'pkg', self::PKG_FIELDS);
        }
    }

    /**
     * Validate pre-built.json configuration
     *
     * @param mixed $data pre-built.json loaded data
     */
    public static function validatePreBuilt(mixed $data): void
    {
        if (!is_array($data)) {
            throw new ValidationException('pre-built.json is broken');
        }

        // Validate all fields using unified method
        self::validateConfigFields($data, 'pre-built', 'pre-built', self::PRE_BUILT_FIELDS);

        // Check for unknown fields
        self::validateAllowedFields($data, 'pre-built', 'pre-built', self::PRE_BUILT_FIELDS);

        // Check match pattern fields (at least one must exist)
        $pattern_fields = ['match-pattern-linux', 'match-pattern-macos', 'match-pattern-windows'];
        $has_pattern = false;

        foreach ($pattern_fields as $field) {
            if (isset($data[$field])) {
                $has_pattern = true;
                // Validate pattern contains required placeholders
                if (!str_contains($data[$field], '{name}')) {
                    throw new ValidationException("pre-built.json [{$field}] must contain {name} placeholder");
                }
                if (!str_contains($data[$field], '{arch}')) {
                    throw new ValidationException("pre-built.json [{$field}] must contain {arch} placeholder");
                }
                if (!str_contains($data[$field], '{os}')) {
                    throw new ValidationException("pre-built.json [{$field}] must contain {os} placeholder");
                }

                // Linux pattern should have libc-related placeholders
                if ($field === 'match-pattern-linux') {
                    if (!str_contains($data[$field], '{libc}')) {
                        throw new ValidationException('pre-built.json [match-pattern-linux] must contain {libc} placeholder');
                    }
                    if (!str_contains($data[$field], '{libcver}')) {
                        throw new ValidationException('pre-built.json [match-pattern-linux] must contain {libcver} placeholder');
                    }
                }
            }
        }

        if (!$has_pattern) {
            throw new ValidationException('pre-built.json must have at least one match-pattern field');
        }
    }

    /**
     * @param mixed   $craft_file craft.yml path
     * @param Command $command    craft command instance
     * @return array{
     *     php-version?: string,
     *     extensions: array<string>,
     *     shared-extensions?: array<string>,
     *     libs?: array<string>,
     *     sapi: array<string>,
     *     debug?: bool,
     *     clean-build?: bool,
     *     build-options?: array<string, mixed>,
     *     download-options?: array<string, mixed>,
     *     extra-env?: array<string, string>,
     *     craft-options?: array{
     *         doctor?: bool,
     *         download?: bool,
     *         build?: bool
     *     }
     * }
     */
    public static function validateAndParseCraftFile(mixed $craft_file, Command $command): array
    {
        $build_options = $command->getApplication()->find('build')->getDefinition()->getOptions();
        $download_options = $command->getApplication()->find('download')->getDefinition()->getOptions();

        try {
            $craft = Yaml::parse(file_get_contents($craft_file));
        } catch (ParseException $e) {
            throw new ValidationException('Craft file is broken: ' . $e->getMessage());
        }
        if (!is_assoc_array($craft)) {
            throw new ValidationException('Craft file is broken');
        }
        // check php-version
        if (isset($craft['php-version'])) {
            // validdate version, accept 8.x, 7.x, 8.x.x, 7.x.x, 8, 7
            $version = strval($craft['php-version']);
            if (!preg_match('/^(\d+)(\.\d+)?(\.\d+)?$/', $version, $matches)) {
                throw new ValidationException('Craft file php-version is invalid');
            }
        }
        // check extensions
        if (!isset($craft['extensions'])) {
            throw new ValidationException('Craft file must have extensions');
        }
        if (is_string($craft['extensions'])) {
            $craft['extensions'] = array_filter(array_map(fn ($x) => trim($x), explode(',', $craft['extensions'])));
        }
        if (!isset($craft['shared-extensions'])) {
            $craft['shared-extensions'] = [];
        }
        if (is_string($craft['shared-extensions'] ?? [])) {
            $craft['shared-extensions'] = array_filter(array_map(fn ($x) => trim($x), explode(',', $craft['shared-extensions'])));
        }
        // check libs
        if (isset($craft['libs']) && is_string($craft['libs'])) {
            $craft['libs'] = array_filter(array_map(fn ($x) => trim($x), explode(',', $craft['libs'])));
        } elseif (!isset($craft['libs'])) {
            $craft['libs'] = [];
        }
        // check sapi
        if (!isset($craft['sapi'])) {
            throw new ValidationException('Craft file must have sapi');
        }
        if (is_string($craft['sapi'])) {
            $craft['sapi'] = array_filter(array_map(fn ($x) => trim($x), explode(',', $craft['sapi'])));
        }
        // debug as boolean
        if (isset($craft['debug'])) {
            $craft['debug'] = filter_var($craft['debug'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $craft['debug'] = false;
        }
        // check clean-build
        $craft['clean-build'] ??= false;
        // check build-options
        if (isset($craft['build-options'])) {
            if (!is_assoc_array($craft['build-options'])) {
                throw new ValidationException('Craft file build-options must be an object');
            }
            foreach ($craft['build-options'] as $key => $value) {
                if (!isset($build_options[$key])) {
                    throw new ValidationException("Craft file build-options {$key} is invalid");
                }
                // check an array
                if ($build_options[$key]->isArray() && !is_array($value)) {
                    throw new ValidationException("Craft file build-options {$key} must be an array");
                }
            }
        } else {
            $craft['build-options'] = [];
        }
        // check download options
        if (isset($craft['download-options'])) {
            if (!is_assoc_array($craft['download-options'])) {
                throw new ValidationException('Craft file download-options must be an object');
            }
            foreach ($craft['download-options'] as $key => $value) {
                if (!isset($download_options[$key])) {
                    throw new ValidationException("Craft file download-options {$key} is invalid");
                }
                // check an array
                if ($download_options[$key]->isArray() && !is_array($value)) {
                    throw new ValidationException("Craft file download-options {$key} must be an array");
                }
            }
        } else {
            $craft['download-options'] = [];
        }
        // check extra-env
        if (isset($craft['extra-env'])) {
            if (!is_assoc_array($craft['extra-env'])) {
                throw new ValidationException('Craft file extra-env must be an object');
            }
        } else {
            $craft['extra-env'] = [];
        }
        // check craft-options
        $craft['craft-options']['doctor'] ??= true;
        $craft['craft-options']['download'] ??= true;
        $craft['craft-options']['build'] ??= true;
        return $craft;
    }

    /**
     * Validate a field based on its global type definition
     *
     * @param  string $field Field name
     * @param  mixed  $value Field value
     * @param  string $name  Item name (for error messages)
     * @param  string $type  Item type (for error messages)
     * @return bool   Returns true if validation passes
     */
    private static function validateFieldType(string $field, mixed $value, string $name, string $type): bool
    {
        // Check if field exists in FIELD_TYPES
        if (!isset(self::FIELD_TYPES[$field])) {
            // Try to strip suffix and check base field name
            $suffixes = ['-windows', '-unix', '-macos', '-linux'];
            $base_field = $field;
            foreach ($suffixes as $suffix) {
                if (str_ends_with($field, $suffix)) {
                    $base_field = substr($field, 0, -strlen($suffix));
                    break;
                }
            }

            if (!isset(self::FIELD_TYPES[$base_field])) {
                // Unknown field is not allowed - strict validation
                throw new ValidationException("{$type} {$name} has unknown field [{$field}]");
            }

            // Use base field type for validation
            $expected_type = self::FIELD_TYPES[$base_field];
        } else {
            $expected_type = self::FIELD_TYPES[$field];
        }

        return match ($expected_type) {
            'string' => is_string($value) ?: throw new ValidationException("{$type} {$name} [{$field}] must be string"),
            'bool' => is_bool($value) ?: throw new ValidationException("{$type} {$name} [{$field}] must be boolean"),
            'array' => is_array($value) ?: throw new ValidationException("{$type} {$name} [{$field}] must be array"),
            'list' => is_list_array($value) ?: throw new ValidationException("{$type} {$name} [{$field}] must be a list"),
            'object' => is_assoc_array($value) ?: throw new ValidationException("{$type} {$name} [{$field}] must be an object"),
            'object|bool' => (is_assoc_array($value) || is_bool($value)) ?: throw new ValidationException("{$type} {$name} [{$field}] must be object or boolean"),
            'object|array' => is_array($value) ?: throw new ValidationException("{$type} {$name} [{$field}] must be an object or array"),
            'callable' => true, // Skip validation for callable
        };
    }

    private static function checkSingleLicense(array $license, string $name): void
    {
        if (!is_assoc_array($license)) {
            throw new ValidationException("source {$name} license must be an object");
        }
        if (!isset($license['type'])) {
            throw new ValidationException("source {$name} license must have type");
        }
        if (!in_array($license['type'], ['file', 'text'])) {
            throw new ValidationException("source {$name} license type is invalid");
        }
        if ($license['type'] === 'file' && !isset($license['path'])) {
            throw new ValidationException("source {$name} license file must have path");
        }
        if ($license['type'] === 'text' && !isset($license['text'])) {
            throw new ValidationException("source {$name} license text must have text");
        }
    }

    /**
     * Validate source type configuration (shared between source.json and pkg.json)
     *
     * @param array  $item        The source/package item to validate
     * @param string $name        The name of the item for error messages
     * @param string $config_type The type of config file ("source" or "pkg")
     */
    private static function validateSourceTypeConfig(array $item, string $name, string $config_type): void
    {
        if (!isset($item['type'])) {
            throw new ValidationException("{$config_type} {$name} must have prop: [type]");
        }
        if (!is_string($item['type'])) {
            throw new ValidationException("{$config_type} {$name} type prop must be string");
        }

        if (!isset(self::SOURCE_TYPE_FIELDS[$item['type']])) {
            throw new ValidationException("{$config_type} {$name} type [{$item['type']}] is invalid");
        }

        [$required, $optional] = self::SOURCE_TYPE_FIELDS[$item['type']];

        // Check required fields exist
        foreach ($required as $prop) {
            if (!isset($item[$prop])) {
                $props = implode('] and [', $required);
                throw new ValidationException("{$config_type} {$name} needs [{$props}] props");
            }
        }

        // Validate field types using global field type definitions
        foreach (array_merge($required, $optional) as $prop) {
            if (isset($item[$prop])) {
                self::validateFieldType($prop, $item[$prop], $name, $config_type);
            }
        }
    }

    /**
     * Validate that fields with suffixes are list arrays
     */
    private static function validateListArrayFields(array $item, string $name, string $type, array $fields, array $suffixes): void
    {
        foreach ($fields as $field) {
            foreach ($suffixes as $suffix) {
                $key = $field . $suffix;
                if (isset($item[$key])) {
                    self::validateFieldType($key, $item[$key], $name, $type);
                }
            }
        }
    }

    /**
     * Validate arg-type fields with suffixes
     */
    private static function validateArgTypeFields(array $item, string $name, array $suffixes): void
    {
        $valid_arg_types = ['enable', 'with', 'with-path', 'custom', 'none', 'enable-path'];

        foreach (array_merge([''], $suffixes) as $suffix) {
            $key = 'arg-type' . $suffix;
            if (isset($item[$key]) && !in_array($item[$key], $valid_arg_types)) {
                throw new ValidationException("ext {$name} {$key} is invalid");
            }
        }
    }

    /**
     * Unified method to validate config fields based on field definitions
     *
     * @param array  $item              Item data to validate
     * @param string $name              Item name for error messages
     * @param string $type              Config type (source, lib, ext, pkg, pre-built)
     * @param array  $field_definitions Field definitions [field_name => required (bool)]
     */
    private static function validateConfigFields(array $item, string $name, string $type, array $field_definitions): void
    {
        foreach ($field_definitions as $field => $required) {
            if ($required && !isset($item[$field])) {
                throw new ValidationException("{$type} {$name} must have [{$field}] field");
            }

            if (isset($item[$field])) {
                self::validateFieldType($field, $item[$field], $name, $type);
            }
        }
    }

    /**
     * Validate that item only contains allowed fields
     * This method checks for unknown fields based on the config type
     *
     * @param array  $item              Item data to validate
     * @param string $name              Item name for error messages
     * @param string $type              Config type (source, lib, ext, pkg, pre-built)
     * @param array  $field_definitions Field definitions [field_name => required (bool)]
     */
    private static function validateAllowedFields(array $item, string $name, string $type, array $field_definitions): void
    {
        // For source and pkg types, we need to check SOURCE_TYPE_FIELDS as well
        $allowed_fields = array_keys($field_definitions);

        // For source/pkg, add allowed fields from SOURCE_TYPE_FIELDS based on the type
        if (in_array($type, ['source', 'pkg']) && isset($item['type'], self::SOURCE_TYPE_FIELDS[$item['type']])) {
            [$required, $optional] = self::SOURCE_TYPE_FIELDS[$item['type']];
            $allowed_fields = array_merge($allowed_fields, $required, $optional);
        }

        // For lib and ext types, add fields with suffixes
        if (in_array($type, ['lib', 'ext'])) {
            $suffixes = ['-windows', '-unix', '-macos', '-linux'];
            $base_fields = ['lib-depends', 'lib-suggests', 'static-libs', 'pkg-configs', 'headers', 'bin'];
            if ($type === 'ext') {
                $base_fields = ['lib-depends', 'lib-suggests', 'ext-depends', 'ext-suggests'];
                // Add arg-type fields
                foreach (array_merge([''], $suffixes) as $suffix) {
                    $allowed_fields[] = 'arg-type' . $suffix;
                }
            }
            foreach ($base_fields as $field) {
                foreach ($suffixes as $suffix) {
                    $allowed_fields[] = $field . $suffix;
                }
            }
            // frameworks is lib-only
            if ($type === 'lib') {
                $allowed_fields[] = 'frameworks';
            }
        }

        // Check each field in item
        foreach (array_keys($item) as $field) {
            if (!in_array($field, $allowed_fields)) {
                throw new ValidationException("{$type} {$name} has unknown field [{$field}]");
            }
        }
    }
}
