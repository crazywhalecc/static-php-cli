<?php

declare(strict_types=1);

namespace StaticPHP\Artifact;

use Symfony\Component\Console\Input\InputOption;

/**
 * Downloader options definition and extraction helper.
 * Used to share download-related options between DownloadCommand and BuildTargetCommand.
 */
class DownloaderOptions
{
    /**
     * Option keys used by the downloader.
     */
    private const array OPTION_KEYS = [
        'with-php',
        'parallel',
        'retry',
        'prefer-source',
        'prefer-binary',
        'prefer-pre-built',
        'source-only',
        'binary-only',
        'ignore-cache',
        'ignore-cache-sources',
        'no-alt',
        'no-shallow-clone',
        'custom-url',
        'custom-git',
        'custom-local',
    ];

    /**
     * Returns all downloader-related InputOption definitions.
     *
     * @param  string        $prefix Optional prefix for option names (e.g., 'dl' becomes '--dl-parallel')
     * @return InputOption[]
     */
    public static function getConsoleOptions(string $prefix = ''): array
    {
        $p = $prefix ? "{$prefix}-" : '';
        $shortP = $prefix ? null : 'P'; // Disable short options when using prefix
        $shortR = $prefix ? null : 'R';
        $shortp = $prefix ? null : 'p';
        $shortU = $prefix ? null : 'U';
        $shortG = $prefix ? null : 'G';
        $shortL = $prefix ? null : 'L';

        return [
            // php version option
            new InputOption("{$p}with-php", null, InputOption::VALUE_REQUIRED, 'PHP version in major.minor format (default 8.4)', '8.4'),

            // download preference options
            new InputOption("{$p}prefer-source", null, InputOption::VALUE_OPTIONAL, 'Prefer source downloads when both source and binary are available', false),
            new InputOption("{$p}prefer-binary", $shortp, InputOption::VALUE_OPTIONAL, 'Prefer binary downloads when both source and binary are available', false),
            new InputOption("{$p}source-only", null, InputOption::VALUE_OPTIONAL, 'Only download source artifacts, skip binary artifacts', false),
            new InputOption("{$p}binary-only", null, InputOption::VALUE_OPTIONAL, 'Only download binary artifacts, skip source artifacts', false),

            // download behavior options
            new InputOption("{$p}parallel", $shortP, InputOption::VALUE_REQUIRED, 'Number of parallel downloads (default 1)', '1'),
            new InputOption("{$p}retry", $shortR, InputOption::VALUE_REQUIRED, 'Number of download retries on failure (default 0)', '0'),
            new InputOption("{$p}ignore-cache", null, InputOption::VALUE_OPTIONAL, 'Ignore some caches when downloading, comma separated, e.g "php-src,curl,openssl"', false),
            new InputOption("{$p}no-alt", null, null, 'Do not use alternative mirror download artifacts for sources'),
            new InputOption("{$p}no-shallow-clone", null, null, 'Do not clone shallowly repositories when downloading sources'),

            // custom overrides
            new InputOption("{$p}custom-url", $shortU, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Specify custom source download url, e.g "php-src:https://example.com/php.tar.gz"'),
            new InputOption("{$p}custom-git", $shortG, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Specify custom source git url, e.g "php-src:master:https://github.com/php/php-src.git"'),
            new InputOption("{$p}custom-local", $shortL, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Specify custom local source path, e.g "php-src:/path/to/php-src"'),

            // deprecated options (for backward compatibility)
            new InputOption("{$p}prefer-pre-built", null, null, 'Deprecated, use `--' . $p . 'prefer-binary` instead'),
            new InputOption("{$p}ignore-cache-sources", null, InputOption::VALUE_OPTIONAL, 'Deprecated, use `--' . $p . 'ignore-cache` instead', false),
        ];
    }

    /**
     * Extract downloader-related options from console input options array.
     * Handles both prefixed and non-prefixed options.
     *
     * @param  array  $allOptions All options from InputInterface::getOptions()
     * @param  string $prefix     The prefix used when defining options (empty for DownloadCommand)
     * @return array  Options array suitable for ArtifactDownloader constructor
     */
    public static function extractFromConsoleOptions(array $allOptions, string $prefix = ''): array
    {
        $result = [];
        $p = $prefix ? "{$prefix}-" : '';

        foreach (self::OPTION_KEYS as $key) {
            $prefixedKey = $p . $key;
            if (array_key_exists($prefixedKey, $allOptions)) {
                // Store with non-prefixed key for ArtifactDownloader
                $result[$key] = $allOptions[$prefixedKey];
            }
        }

        return $result;
    }
}
