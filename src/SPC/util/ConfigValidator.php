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
     * Validate source.json
     *
     * @param array $data source.json data array
     */
    public static function validateSource(array $data): void
    {
        foreach ($data as $name => $src) {
            // Validate basic source type configuration
            self::validateSourceTypeConfig($src, $name, 'source');

            // Check source-specific fields
            // check if alt is valid
            if (isset($src['alt'])) {
                if (!is_assoc_array($src['alt']) && !is_bool($src['alt'])) {
                    throw new ValidationException("source {$name} alt must be object or boolean");
                }
                if (is_assoc_array($src['alt'])) {
                    // validate alt source recursively
                    self::validateSource([$name . '_alt' => $src['alt']]);
                }
            }

            // check if provide-pre-built is boolean
            if (isset($src['provide-pre-built']) && !is_bool($src['provide-pre-built'])) {
                throw new ValidationException("source {$name} provide-pre-built must be boolean");
            }

            // check if prefer-stable is boolean
            if (isset($src['prefer-stable']) && !is_bool($src['prefer-stable'])) {
                throw new ValidationException("source {$name} prefer-stable must be boolean");
            }

            // check if license is valid
            if (isset($src['license'])) {
                if (!is_array($src['license'])) {
                    throw new ValidationException("source {$name} license must be an object or array");
                }
                if (is_assoc_array($src['license'])) {
                    self::checkSingleLicense($src['license'], $name);
                } elseif (is_list_array($src['license'])) {
                    foreach ($src['license'] as $license) {
                        if (!is_assoc_array($license)) {
                            throw new ValidationException("source {$name} license must be an object or array");
                        }
                        self::checkSingleLicense($license, $name);
                    }
                } else {
                    throw new ValidationException("source {$name} license must be an object or array");
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
        // check each lib
        foreach ($data as $name => $lib) {
            // check if lib is an assoc array
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
            // check if source is string
            if (isset($lib['source']) && !is_string($lib['source'])) {
                throw new ValidationException("lib {$name} source must be string");
            }
            // check if [lib-depends|lib-suggests|static-libs|headers|bin][-windows|-unix|-macos|-linux] are valid list array
            $suffixes = ['', '-windows', '-unix', '-macos', '-linux'];
            foreach ($suffixes as $suffix) {
                if (isset($lib['lib-depends' . $suffix]) && !is_list_array($lib['lib-depends' . $suffix])) {
                    throw new ValidationException("lib {$name} lib-depends must be a list");
                }
                if (isset($lib['lib-suggests' . $suffix]) && !is_list_array($lib['lib-suggests' . $suffix])) {
                    throw new ValidationException("lib {$name} lib-suggests must be a list");
                }
                if (isset($lib['static-libs' . $suffix]) && !is_list_array($lib['static-libs' . $suffix])) {
                    throw new ValidationException("lib {$name} static-libs must be a list");
                }
                if (isset($lib['pkg-configs' . $suffix]) && !is_list_array($lib['pkg-configs' . $suffix])) {
                    throw new ValidationException("lib {$name} pkg-configs must be a list");
                }
                if (isset($lib['headers' . $suffix]) && !is_list_array($lib['headers' . $suffix])) {
                    throw new ValidationException("lib {$name} headers must be a list");
                }
                if (isset($lib['bin' . $suffix]) && !is_list_array($lib['bin' . $suffix])) {
                    throw new ValidationException("lib {$name} bin must be a list");
                }
            }
            // check if frameworks is a list array
            if (isset($lib['frameworks']) && !is_list_array($lib['frameworks'])) {
                throw new ValidationException("lib {$name} frameworks must be a list");
            }
        }
    }

    public static function validateExts(mixed $data): void
    {
        if (!is_array($data)) {
            throw new ValidationException('ext.json is broken');
        }
        // check each extension
        foreach ($data as $name => $ext) {
            // check if ext is an assoc array
            if (!is_assoc_array($ext)) {
                throw new ValidationException("ext {$name} is not an object");
            }
            // check if ext has valid type
            if (!in_array($ext['type'] ?? '', ['builtin', 'external', 'addon', 'wip'])) {
                throw new ValidationException("ext {$name} type is invalid");
            }
            // check if external ext has source
            if (($ext['type'] ?? '') === 'external' && !isset($ext['source'])) {
                throw new ValidationException("ext {$name} does not assign any source");
            }
            // check if source is string
            if (isset($ext['source']) && !is_string($ext['source'])) {
                throw new ValidationException("ext {$name} source must be string");
            }
            // check if support is valid
            if (isset($ext['support']) && !is_assoc_array($ext['support'])) {
                throw new ValidationException("ext {$name} support must be an object");
            }
            // check if notes is boolean
            if (isset($ext['notes']) && !is_bool($ext['notes'])) {
                throw new ValidationException("ext {$name} notes must be boolean");
            }
            // check if [lib-depends|lib-suggests|ext-depends][-windows|-unix|-macos|-linux] are valid list array
            $suffixes = ['', '-windows', '-unix', '-macos', '-linux'];
            foreach ($suffixes as $suffix) {
                if (isset($ext['lib-depends' . $suffix]) && !is_list_array($ext['lib-depends' . $suffix])) {
                    throw new ValidationException("ext {$name} lib-depends must be a list");
                }
                if (isset($ext['lib-suggests' . $suffix]) && !is_list_array($ext['lib-suggests' . $suffix])) {
                    throw new ValidationException("ext {$name} lib-suggests must be a list");
                }
                if (isset($ext['ext-depends' . $suffix]) && !is_list_array($ext['ext-depends' . $suffix])) {
                    throw new ValidationException("ext {$name} ext-depends must be a list");
                }
            }
            // check if arg-type is valid
            if (isset($ext['arg-type'])) {
                $valid_arg_types = ['enable', 'with', 'with-path', 'custom', 'none', 'enable-path'];
                if (!in_array($ext['arg-type'], $valid_arg_types)) {
                    throw new ValidationException("ext {$name} arg-type is invalid");
                }
            }
            // check if arg-type with suffix is valid
            foreach ($suffixes as $suffix) {
                if (isset($ext['arg-type' . $suffix])) {
                    $valid_arg_types = ['enable', 'with', 'with-path', 'custom', 'none', 'enable-path'];
                    if (!in_array($ext['arg-type' . $suffix], $valid_arg_types)) {
                        throw new ValidationException("ext {$name} arg-type{$suffix} is invalid");
                    }
                }
            }
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

            // Check pkg-specific fields
            // check if extract-files is valid
            if (isset($pkg['extract-files'])) {
                if (!is_assoc_array($pkg['extract-files'])) {
                    throw new ValidationException("pkg {$name} extract-files must be an object");
                }
                // check each extract file mapping
                foreach ($pkg['extract-files'] as $source => $target) {
                    if (!is_string($source) || !is_string($target)) {
                        throw new ValidationException("pkg {$name} extract-files mapping must be string to string");
                    }
                }
            }
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

        // Check required fields
        if (!isset($data['repo'])) {
            throw new ValidationException('pre-built.json must have [repo] field');
        }
        if (!is_string($data['repo'])) {
            throw new ValidationException('pre-built.json [repo] must be string');
        }

        // Check optional prefer-stable field
        if (isset($data['prefer-stable']) && !is_bool($data['prefer-stable'])) {
            throw new ValidationException('pre-built.json [prefer-stable] must be boolean');
        }

        // Check match pattern fields (at least one must exist)
        $pattern_fields = ['match-pattern-linux', 'match-pattern-macos', 'match-pattern-windows'];
        $has_pattern = false;

        foreach ($pattern_fields as $field) {
            if (isset($data[$field])) {
                $has_pattern = true;
                if (!is_string($data[$field])) {
                    throw new ValidationException("pre-built.json [{$field}] must be string");
                }
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
        if (!in_array($item['type'], ['filelist', 'git', 'ghtagtar', 'ghtar', 'ghrel', 'url', 'custom'])) {
            throw new ValidationException("{$config_type} {$name} type [{$item['type']}] is invalid");
        }

        // Validate type-specific requirements
        switch ($item['type']) {
            case 'filelist':
                if (!isset($item['url'], $item['regex'])) {
                    throw new ValidationException("{$config_type} {$name} needs [url] and [regex] props");
                }
                if (!is_string($item['url']) || !is_string($item['regex'])) {
                    throw new ValidationException("{$config_type} {$name} [url] and [regex] must be string");
                }
                break;
            case 'git':
                if (!isset($item['url'], $item['rev'])) {
                    throw new ValidationException("{$config_type} {$name} needs [url] and [rev] props");
                }
                if (!is_string($item['url']) || !is_string($item['rev'])) {
                    throw new ValidationException("{$config_type} {$name} [url] and [rev] must be string");
                }
                if (isset($item['path']) && !is_string($item['path'])) {
                    throw new ValidationException("{$config_type} {$name} [path] must be string");
                }
                break;
            case 'ghtagtar':
            case 'ghtar':
                if (!isset($item['repo'])) {
                    throw new ValidationException("{$config_type} {$name} needs [repo] prop");
                }
                if (!is_string($item['repo'])) {
                    throw new ValidationException("{$config_type} {$name} [repo] must be string");
                }
                if (isset($item['path']) && !is_string($item['path'])) {
                    throw new ValidationException("{$config_type} {$name} [path] must be string");
                }
                break;
            case 'ghrel':
                if (!isset($item['repo'], $item['match'])) {
                    throw new ValidationException("{$config_type} {$name} needs [repo] and [match] props");
                }
                if (!is_string($item['repo']) || !is_string($item['match'])) {
                    throw new ValidationException("{$config_type} {$name} [repo] and [match] must be string");
                }
                break;
            case 'url':
                if (!isset($item['url'])) {
                    throw new ValidationException("{$config_type} {$name} needs [url] prop");
                }
                if (!is_string($item['url'])) {
                    throw new ValidationException("{$config_type} {$name} [url] must be string");
                }
                if (isset($item['filename']) && !is_string($item['filename'])) {
                    throw new ValidationException("{$config_type} {$name} [filename] must be string");
                }
                if (isset($item['path']) && !is_string($item['path'])) {
                    throw new ValidationException("{$config_type} {$name} [path] must be string");
                }
                break;
            case 'custom':
                // custom type has no specific requirements
                break;
        }
    }
}
