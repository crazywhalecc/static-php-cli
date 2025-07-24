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
     * 验证 source.json
     *
     * @param  array               $data source.json 加载后的数据
     * @throws ValidationException
     */
    public static function validateSource(array $data): void
    {
        foreach ($data as $name => $src) {
            isset($src['type']) || throw new ValidationException("source {$name} must have prop: [type]");
            is_string($src['type']) || throw new ValidationException("source {$name} type prop must be string");
            in_array($src['type'], ['filelist', 'git', 'ghtagtar', 'ghtar', 'ghrel', 'url', 'custom']) || throw new ValidationException("source {$name} type [{$src['type']}] is invalid");
            switch ($src['type']) {
                case 'filelist':
                    isset($src['url'], $src['regex']) || throw new ValidationException("source {$name} needs [url] and [regex] props");
                    is_string($src['url']) && is_string($src['regex']) || throw new ValidationException("source {$name} [url] and [regex] must be string");
                    break;
                case 'git':
                    isset($src['url'], $src['rev']) || throw new ValidationException("source {$name} needs [url] and [rev] props");
                    is_string($src['url']) && is_string($src['rev']) || throw new ValidationException("source {$name} [url] and [rev] must be string");
                    is_string($src['path'] ?? '') || throw new ValidationException("source {$name} [path] must be string");
                    break;
                case 'ghtagtar':
                case 'ghtar':
                    isset($src['repo']) || throw new ValidationException("source {$name} needs [repo] prop");
                    is_string($src['repo']) || throw new ValidationException("source {$name} [repo] must be string");
                    is_string($src['path'] ?? '') || throw new ValidationException("source {$name} [path] must be string");
                    break;
                case 'ghrel':
                    isset($src['repo'], $src['match']) || throw new ValidationException("source {$name} needs [repo] and [match] props");
                    is_string($src['repo']) && is_string($src['match']) || throw new ValidationException("source {$name} [repo] and [match] must be string");
                    break;
                case 'url':
                    isset($src['url']) || throw new ValidationException("source {$name} needs [url] prop");
                    is_string($src['url']) || throw new ValidationException("source {$name} [url] must be string");
                    break;
            }
        }
    }

    /**
     * @throws ValidationException
     */
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
            // check if [lib-depends|lib-suggests|static-libs][-windows|-unix|-macos|-linux] are valid list array
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
            }
            // check if frameworks is a list array
            if (isset($lib['frameworks']) && !is_list_array($lib['frameworks'])) {
                throw new ValidationException("lib {$name} frameworks must be a list");
            }
        }
    }

    /**
     * @throws ValidationException
     */
    public static function validateExts(mixed $data): void
    {
        is_array($data) || throw new ValidationException('ext.json is broken');
    }

    /**
     * @throws ValidationException
     */
    public static function validatePkgs(mixed $data): void
    {
        is_array($data) || throw new ValidationException('pkg.json is broken');
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
     * @throws ValidationException
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
}
