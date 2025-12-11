<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader;

use StaticPHP\Exception\DownloaderException;
use StaticPHP\Util\FileSystem;

class DownloadResult
{
    /**
     * @param string      $cache_type Type of cache: 'archive', 'git', or 'local'
     * @param null|string $filename   Filename for archive type
     * @param null|string $dirname    Directory name for git/local type
     * @param mixed       $extract    Extraction path or configuration
     * @param bool        $verified   Whether the download has been verified (hash check)
     * @param null|string $version    Version of the downloaded artifact (e.g., "1.2.3", "v2.0.0")
     * @param array       $metadata   Additional metadata (e.g., commit hash, release notes, etc.)
     */
    private function __construct(
        public readonly string $cache_type,
        public readonly array $config,
        public readonly ?string $filename = null,
        public readonly ?string $dirname = null,
        public mixed $extract = null,
        public bool $verified = false,
        public readonly ?string $version = null,
        public readonly array $metadata = [],
    ) {
        switch ($this->cache_type) {
            case 'archive':
            case 'file':
                $this->filename !== null ?: throw new DownloaderException('Archive/file download result must have a filename.');
                $fn = FileSystem::isRelativePath($this->filename) ? (DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $this->filename) : $this->filename;
                file_exists($fn) ?: throw new DownloaderException("Downloaded archive file does not exist: {$fn}");
                break;
            case 'git':
            case 'local':
                $this->dirname !== null ?: throw new DownloaderException('Git/local download result must have a dirname.');
                $dn = FileSystem::isRelativePath($this->dirname) ? (DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $this->dirname) : $this->dirname;
                file_exists($dn) ?: throw new DownloaderException("Downloaded directory does not exist: {$dn}");
                break;
        }
    }

    /**
     * Create a download result for an archive file.
     *
     * @param string      $filename Filename of the downloaded archive
     * @param mixed       $extract  Extraction path or configuration
     * @param bool        $verified Whether the archive has been hash-verified
     * @param null|string $version  Version string of the downloaded artifact
     * @param array       $metadata Additional metadata
     */
    public static function archive(
        string $filename,
        array $config,
        mixed $extract = null,
        bool $verified = false,
        ?string $version = null,
        array $metadata = []
    ): DownloadResult {
        // judge if it is archive or just a pure file
        $cache_type = self::isArchiveFile($filename) ? 'archive' : 'file';
        return new self($cache_type, config: $config, filename: $filename, extract: $extract, verified: $verified, version: $version, metadata: $metadata);
    }

    public static function file(
        string $filename,
        array $config,
        bool $verified = false,
        ?string $version = null,
        array $metadata = []
    ): DownloadResult {
        $cache_type = self::isArchiveFile($filename) ? 'archive' : 'file';
        return new self($cache_type, config: $config, filename: $filename, verified: $verified, version: $version, metadata: $metadata);
    }

    /**
     * Create a download result for a git clone.
     *
     * @param string      $dirname  Directory name of the cloned repository
     * @param mixed       $extract  Extraction path or configuration
     * @param null|string $version  Version string (tag, branch, or commit)
     * @param array       $metadata Additional metadata (e.g., commit hash)
     */
    public static function git(string $dirname, array $config, mixed $extract = null, ?string $version = null, array $metadata = []): DownloadResult
    {
        return new self('git', config: $config, dirname: $dirname, extract: $extract, version: $version, metadata: $metadata);
    }

    /**
     * Create a download result for a local directory.
     *
     * @param string      $dirname  Directory name
     * @param mixed       $extract  Extraction path or configuration
     * @param null|string $version  Version string if known
     * @param array       $metadata Additional metadata
     */
    public static function local(string $dirname, array $config, mixed $extract = null, ?string $version = null, array $metadata = []): DownloadResult
    {
        return new self('local', config: $config, dirname: $dirname, extract: $extract, version: $version, metadata: $metadata);
    }

    /**
     * Check if version information is available.
     */
    public function hasVersion(): bool
    {
        return $this->version !== null;
    }

    /**
     * Get a metadata value by key.
     *
     * @param string $key     Metadata key
     * @param mixed  $default Default value if key doesn't exist
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Create a new DownloadResult with updated version.
     * (Immutable pattern - returns a new instance)
     */
    public function withVersion(string $version): self
    {
        return new self(
            $this->cache_type,
            $this->config,
            $this->filename,
            $this->dirname,
            $this->extract,
            $this->verified,
            $version,
            $this->metadata
        );
    }

    /**
     * Create a new DownloadResult with additional metadata.
     * (Immutable pattern - returns a new instance)
     */
    public function withMeta(string $key, mixed $value): self
    {
        return new self(
            $this->cache_type,
            $this->config,
            $this->filename,
            $this->dirname,
            $this->extract,
            $this->verified,
            $this->version,
            array_merge($this->metadata, [$key => $value])
        );
    }

    /**
     * Check
     */
    private static function isArchiveFile(string $filename): bool
    {
        $archive_extensions = [
            'zip', 'tar', 'tar.gz', 'tgz', 'tar.bz2', 'tbz2', 'tar.xz', 'txz', 'rar', '7z',
        ];
        $lower_filename = strtolower($filename);
        return array_any($archive_extensions, fn ($ext) => str_ends_with($lower_filename, '.' . $ext));
    }
}
