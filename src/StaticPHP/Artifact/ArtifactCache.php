<?php

declare(strict_types=1);

namespace StaticPHP\Artifact;

use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Util\FileSystem;

class ArtifactCache
{
    /**
     * @var array<string, array{
     *     source: null|array{
     *         lock_type: 'binary'|'source',
     *         cache_type: 'archive'|'file'|'git'|'local',
     *         filename?: string,
     *         dirname?: string,
     *         extract: null|'&custom'|string,
     *         hash: null|string
     *     },
     *     binary: array{
     *         windows-x86_64?: null|array{
     *             lock_type: 'binary'|'source',
     *             cache_type: 'archive'|'file'|'git'|'local',
     *             filename?: string,
     *             dirname?: string,
     *             extract: null|'&custom'|string,
     *             hash: null|string,
     *             version?: null|string
     *         }
     *     }
     * }>
     */
    protected array $cache = [];

    /**
     * @param string $cache_file Lock file position
     */
    public function __construct(protected string $cache_file = DOWNLOAD_PATH . '/.cache.json')
    {
        if (!file_exists($this->cache_file)) {
            logger()->debug("Cache file does not exist, creating new one at {$this->cache_file}");
            FileSystem::createDir(dirname($this->cache_file));
            file_put_contents($this->cache_file, json_encode([]));
        } else {
            $content = file_get_contents($this->cache_file);
            $this->cache = json_decode($content ?: '{}', true) ?? [];
        }
    }

    /**
     * Checks if the source of an artifact is already downloaded.
     *
     * @param string $artifact_name Artifact name
     * @param bool   $compare_hash  Whether to compare hash of the downloaded source
     */
    public function isSourceDownloaded(string $artifact_name, bool $compare_hash = false): bool
    {
        $item = $this->cache[$artifact_name] ?? null;
        if ($item === null) {
            return false;
        }
        return $this->isObjectDownloaded($this->cache[$artifact_name]['source'] ?? null, $compare_hash);
    }

    /**
     * Check if the binary of an artifact for target OS is already downloaded.
     *
     * @param string $artifact_name Artifact name
     * @param string $target_os     Target OS (accepts {windows|linux|macos}-{x86_64|aarch64})
     * @param bool   $compare_hash  Whether to compare hash of the downloaded binary
     */
    public function isBinaryDownloaded(string $artifact_name, string $target_os, bool $compare_hash = false): bool
    {
        $item = $this->cache[$artifact_name] ?? null;
        if ($item === null) {
            return false;
        }
        return $this->isObjectDownloaded($this->cache[$artifact_name]['binary'][$target_os] ?? null, $compare_hash);
    }

    /**
     * Lock the downloaded artifact info into cache.
     *
     * @param Artifact|string   $artifact        Artifact instance
     * @param 'binary'|'source' $lock_type       Lock type ('source'|'binary')
     * @param DownloadResult    $download_result Download result object
     * @param null|string       $platform        Target platform string for binary lock, null for source lock
     */
    public function lock(Artifact|string $artifact, string $lock_type, DownloadResult $download_result, ?string $platform = null): void
    {
        $artifact_name = $artifact instanceof Artifact ? $artifact->getName() : $artifact;
        if (!isset($this->cache[$artifact_name])) {
            $this->cache[$artifact_name] = [
                'source' => null,
                'binary' => [],
            ];
        }
        $obj = null;
        if ($download_result->cache_type === 'archive') {
            $obj = [
                'lock_type' => $lock_type,
                'cache_type' => 'archive',
                'filename' => $download_result->filename,
                'extract' => $download_result->extract,
                'hash' => sha1_file(DOWNLOAD_PATH . '/' . $download_result->filename),
                'version' => $download_result->version,
                'config' => $download_result->config,
            ];
        } elseif ($download_result->cache_type === 'file') {
            $obj = [
                'lock_type' => $lock_type,
                'cache_type' => 'file',
                'filename' => $download_result->filename,
                'extract' => $download_result->extract,
                'hash' => sha1_file(DOWNLOAD_PATH . '/' . $download_result->filename),
                'version' => $download_result->version,
                'config' => $download_result->config,
            ];
        } elseif ($download_result->cache_type === 'git') {
            $obj = [
                'lock_type' => $lock_type,
                'cache_type' => 'git',
                'dirname' => $download_result->dirname,
                'extract' => $download_result->extract,
                'hash' => trim(exec('cd ' . escapeshellarg(DOWNLOAD_PATH . '/' . $download_result->dirname) . ' && ' . SPC_GIT_EXEC . ' rev-parse HEAD')),
                'version' => $download_result->version,
                'config' => $download_result->config,
            ];
        } elseif ($download_result->cache_type === 'local') {
            $obj = [
                'lock_type' => $lock_type,
                'cache_type' => 'local',
                'dirname' => $download_result->dirname,
                'extract' => $download_result->extract,
                'hash' => null,
                'version' => $download_result->version,
                'config' => $download_result->config,
            ];
        }
        if ($obj === null) {
            throw new SPCInternalException("Invalid download result for locking artifact {$artifact_name}");
        }
        if ($lock_type === 'binary') {
            if ($platform === null) {
                throw new SPCInternalException("Invalid download result for locking binary artifact {$artifact_name}: platform cannot be null");
            }
            $obj['platform'] = $platform;
        }
        if ($lock_type === 'source') {
            $this->cache[$artifact_name]['source'] = $obj;
        } elseif ($lock_type === 'binary') {
            $this->cache[$artifact_name]['binary'][$platform] = $obj;
        } else {
            throw new SPCInternalException("Invalid lock type '{$lock_type}' for artifact {$artifact_name}");
        }
        // save cache to file
        file_put_contents($this->cache_file, json_encode($this->cache, JSON_PRETTY_PRINT));
    }

    /**
     * Get source cache info for an artifact.
     *
     * @param  string     $artifact_name Artifact name
     * @return null|array Cache info array or null if not found
     */
    public function getSourceInfo(string $artifact_name): ?array
    {
        return $this->cache[$artifact_name]['source'] ?? null;
    }

    /**
     * Get binary cache info for an artifact on specific platform.
     *
     * @param string $artifact_name Artifact name
     * @param string $platform      Platform string (e.g., 'linux-x86_64')
     * @return null|array{
     *     lock_type: 'binary'|'source',
     *     cache_type: 'archive'|'git'|'local',
     *     filename?: string,
     *     extract: null|'&custom'|string,
     *     hash: null|string,
     *     dirname?: string,
     *     version?: null|string
     * } Cache info array or null if not found
     */
    public function getBinaryInfo(string $artifact_name, string $platform): ?array
    {
        return $this->cache[$artifact_name]['binary'][$platform] ?? null;
    }

    /**
     * Get the full path to the cached file/directory.
     *
     * @param  array  $cache_info Cache info from getSourceInfo() or getBinaryInfo()
     * @return string Full path to the cached file or directory
     */
    public function getCacheFullPath(array $cache_info): string
    {
        return match ($cache_info['cache_type']) {
            'archive', 'file' => DOWNLOAD_PATH . '/' . $cache_info['filename'],
            'git' => DOWNLOAD_PATH . '/' . $cache_info['dirname'],
            'local' => $cache_info['dirname'], // local dirname is absolute path
            default => throw new SPCInternalException("Unknown cache type: {$cache_info['cache_type']}"),
        };
    }

    /**
     * Remove source cache entry for an artifact.
     *
     * @param string $artifact_name Artifact name
     * @param bool   $delete_file   Whether to also delete the cached file/directory
     */
    public function removeSource(string $artifact_name, bool $delete_file = false): void
    {
        $source_info = $this->getSourceInfo($artifact_name);
        if ($source_info === null) {
            return;
        }

        // Optionally delete the actual file/directory
        if ($delete_file) {
            $path = $this->getCacheFullPath($source_info);
            if (in_array($source_info['cache_type'], ['archive', 'file']) && file_exists($path)) {
                unlink($path);
                logger()->debug("Deleted cached archive: {$path}");
            } elseif ($source_info['cache_type'] === 'git' && is_dir($path)) {
                FileSystem::removeDir($path);
                logger()->debug("Deleted cached git repository: {$path}");
            }
        }

        // Remove from cache
        $this->cache[$artifact_name]['source'] = null;
        $this->save();
        logger()->debug("Removed source cache entry for [{$artifact_name}]");
    }

    /**
     * Remove binary cache entry for an artifact on specific platform.
     *
     * @param string $artifact_name Artifact name
     * @param string $platform      Platform string (e.g., 'linux-x86_64')
     * @param bool   $delete_file   Whether to also delete the cached file/directory
     */
    public function removeBinary(string $artifact_name, string $platform, bool $delete_file = false): void
    {
        $binary_info = $this->getBinaryInfo($artifact_name, $platform);
        if ($binary_info === null) {
            return;
        }

        // Optionally delete the actual file/directory
        if ($delete_file) {
            $path = $this->getCacheFullPath($binary_info);
            if (in_array($binary_info['cache_type'], ['archive', 'file']) && file_exists($path)) {
                unlink($path);
                logger()->debug("Deleted cached binary archive: {$path}");
            } elseif ($binary_info['cache_type'] === 'git' && is_dir($path)) {
                FileSystem::removeDir($path);
                logger()->debug("Deleted cached binary git repository: {$path}");
            }
        }

        // Remove from cache
        unset($this->cache[$artifact_name]['binary'][$platform]);
        $this->save();
        logger()->debug("Removed binary cache entry for [{$artifact_name}] on platform [{$platform}]");
    }

    /**
     * Save cache to file.
     */
    public function save(): void
    {
        file_put_contents($this->cache_file, json_encode($this->cache, JSON_PRETTY_PRINT));
    }

    private function isObjectDownloaded(?array $object, bool $compare_hash = false): bool
    {
        if ($object === null) {
            return false;
        }
        // check if source is cached and file/dir exists in downloads/ dir
        return match ($object['cache_type'] ?? null) {
            'archive', 'file' => isset($object['filename']) &&
                file_exists(DOWNLOAD_PATH . '/' . $object['filename']) &&
                (!$compare_hash || (
                    isset($object['hash']) &&
                    sha1_file(DOWNLOAD_PATH . '/' . $object['filename']) === $object['hash']
                )),
            'git' => isset($object['dirname']) &&
                is_dir(DOWNLOAD_PATH . '/' . $object['dirname'] . '/.git') &&
                (!$compare_hash || (
                    isset($object['hash']) &&
                    trim(exec('cd ' . escapeshellarg(DOWNLOAD_PATH . '/' . $object['dirname']) . ' && ' . SPC_GIT_EXEC . ' rev-parse HEAD')) === $object['hash']
                )),
            'local' => isset($object['dirname']) &&
                is_dir($object['dirname']), // local dirname is absolute path
            default => false,
        };
    }
}
