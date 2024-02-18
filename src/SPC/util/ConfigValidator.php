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
        is_array($data) || throw new ValidationException('lib.json is broken');
        foreach ($data as $name => $lib) {
            isset($lib['source']) || throw new ValidationException("lib {$name} does not assign any source");
            is_string($lib['source']) || throw new ValidationException("lib {$name} source must be string");
            empty($source_data) || isset($source_data[$lib['source']]) || throw new ValidationException("lib {$name} assigns an invalid source: {$lib['source']}");
            !isset($lib['lib-depends']) || !is_assoc_array($lib['lib-depends']) || throw new ValidationException("lib {$name} dependencies must be a list");
            !isset($lib['lib-suggests']) || !is_assoc_array($lib['lib-suggests']) || throw new ValidationException("lib {$name} suggested dependencies must be a list");
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
