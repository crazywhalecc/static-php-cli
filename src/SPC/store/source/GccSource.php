<?php

declare(strict_types=1);

namespace SPC\store\source;

use JetBrains\PhpStorm\ArrayShape;
use SPC\exception\DownloaderException;
use SPC\exception\FileSystemException;
use SPC\store\Downloader;

class GccSource extends CustomSourceBase
{
    public const NAME = 'gcc';

    /**
     * @throws DownloaderException
     * @throws FileSystemException
     */
    public function fetch(bool $force = false, ?array $config = null, int $lock_as = SPC_DOWNLOAD_SOURCE): void
    {
        $version = self::getGccVersion();
        Downloader::downloadSource('gcc', self::getGccSourceInfo($version), $force);
    }

    /**
     * Get the installed GCC version
     *
     * @return string              The GCC version (e.g., "11.5.0")
     * @throws DownloaderException
     */
    public static function getGccVersion(): string
    {
        $cc = getenv('CC') ?: 'gcc';
        $output = [];
        $return_var = 0;

        exec("{$cc} --version", $output, $return_var);

        if ($return_var !== 0 || empty($output)) {
            throw new DownloaderException("Failed to get GCC version using {$cc}");
        }

        // Parse the version from the output
        // Example output: "gcc (GCC) 11.5.0" or "gcc (Ubuntu 4.8.5-4ubuntu2) 4.8.5"
        $version_line = $output[0];
        if (preg_match('/\s(\d+\.\d+\.\d+)/', $version_line, $matches)) {
            // official ftp mirrors only keep .0 releases
            return preg_replace('/\.\d+$/', '.0', $matches[1]);
        }

        throw new DownloaderException("Could not parse GCC version from: {$version_line}");
    }

    /**
     * Get the GCC source information for a specific version
     *
     * @param  string $version The GCC version to download
     * @return array  The source information
     */
    #[ArrayShape(['type' => 'string', 'url' => 'string'])]
    public static function getGccSourceInfo(string $version): array
    {
        return [
            'type' => 'url',
            'url' => "https://ftp.gnu.org/gnu/gcc/gcc-{$version}/gcc-{$version}.tar.xz",
        ];
    }
}
