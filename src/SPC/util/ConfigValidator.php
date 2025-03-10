<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\ValidationException;

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
}
