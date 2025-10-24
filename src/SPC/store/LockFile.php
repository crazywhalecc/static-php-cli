<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\exception\SPCInternalException;
use SPC\exception\WrongUsageException;

class LockFile
{
    public const string LOCK_FILE = DOWNLOAD_PATH . '/.lock.json';

    private static ?array $lock_file_content = null;

    /**
     * Get a lock entry by its name.
     *
     * @param string $lock_name Lock name to retrieve
     * @return null|array{
     *     source_type: string,
     *     filename: ?string,
     *     dirname: ?string,
     *     move_path: ?string,
     *     lock_as: int,
     *     hash: string
     * } Returns the lock entry as an associative array if it exists, or null if it does not
     */
    public static function get(string $lock_name): ?array
    {
        self::init();

        // Return the specific lock entry if it exists, otherwise return an empty array
        $result = self::$lock_file_content[$lock_name] ?? null;

        // Add old `dir` compatibility
        if (($result['source_type'] ?? null) === 'dir') {
            logger()->warning("Lock entry for '{$lock_name}' has 'source_type' set to 'dir', which is deprecated. Please re-download your dependencies.");
            $result['source_type'] = SPC_SOURCE_GIT;
        }

        return $result;
    }

    /**
     * Check if a lock file exists for a given lock name.
     *
     * @param string $lock_name Lock name to check
     */
    public static function isLockFileExists(string $lock_name): bool
    {
        return match (self::get($lock_name)['source_type'] ?? null) {
            SPC_SOURCE_ARCHIVE => file_exists(DOWNLOAD_PATH . '/' . (self::get($lock_name)['filename'] ?? '.never-exist-file')),
            SPC_SOURCE_GIT, SPC_SOURCE_LOCAL => is_dir(DOWNLOAD_PATH . '/' . (self::get($lock_name)['dirname'] ?? '.never-exist-dir')),
            default => false,
        };
    }

    /**
     * Put a lock entry into the lock file.
     *
     * @param string     $lock_name    Lock name to set or remove
     * @param null|array $lock_content lock content to set, or null to remove the lock entry
     */
    public static function put(string $lock_name, ?array $lock_content): void
    {
        self::init();

        if ($lock_content === null && isset(self::$lock_file_content[$lock_name])) {
            self::removeLockFileIfExists(self::$lock_file_content[$lock_name]);
            unset(self::$lock_file_content[$lock_name]);
        } else {
            self::$lock_file_content[$lock_name] = $lock_content;
        }

        // Write the updated lock data back to the file
        FileSystem::createDir(dirname(self::LOCK_FILE));
        file_put_contents(self::LOCK_FILE, json_encode(self::$lock_file_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get the full path of a lock file or directory based on the lock options.
     *
     * @param  array  $lock_options lock item options, must contain 'source_type', 'filename' or 'dirname'
     * @return string the absolute path to the lock file or directory
     */
    public static function getLockFullPath(array $lock_options): string
    {
        return match ($lock_options['source_type']) {
            SPC_SOURCE_ARCHIVE => FileSystem::isRelativePath($lock_options['filename']) ? (DOWNLOAD_PATH . '/' . $lock_options['filename']) : $lock_options['filename'],
            SPC_SOURCE_GIT, SPC_SOURCE_LOCAL => FileSystem::isRelativePath($lock_options['dirname']) ? (DOWNLOAD_PATH . '/' . $lock_options['dirname']) : $lock_options['dirname'],
            default => throw new WrongUsageException("Unknown source type: {$lock_options['source_type']}"),
        };
    }

    public static function getExtractPath(string $lock_name, string $default_path): ?string
    {
        $lock = self::get($lock_name);
        if ($lock === null) {
            return null;
        }

        // If move_path is set, use it; otherwise, use the default extract directory
        if (isset($lock['move_path'])) {
            if (FileSystem::isRelativePath($lock['move_path'])) {
                // If move_path is relative, prepend the default extract directory
                return match ($lock['lock_as']) {
                    SPC_DOWNLOAD_SOURCE, SPC_DOWNLOAD_PRE_BUILT => FileSystem::convertPath(SOURCE_PATH . '/' . $lock['move_path']),
                    SPC_DOWNLOAD_PACKAGE => FileSystem::convertPath(PKG_ROOT_PATH . '/' . $lock['move_path']),
                    default => throw new WrongUsageException("Unknown lock type: {$lock['lock_as']}"),
                };
            }
            return FileSystem::convertPath($lock['move_path']);
        }
        return FileSystem::convertPath($default_path);
    }

    /**
     * Get the hash of the lock source based on the lock options.
     *
     * @param  array  $lock_options Lock options
     * @return string Hash of the lock source
     */
    public static function getLockSourceHash(array $lock_options): string
    {
        $result = match ($lock_options['source_type']) {
            SPC_SOURCE_ARCHIVE => sha1_file(DOWNLOAD_PATH . '/' . $lock_options['filename']),
            SPC_SOURCE_GIT => exec('cd ' . escapeshellarg(DOWNLOAD_PATH . '/' . $lock_options['dirname']) . ' && ' . SPC_GIT_EXEC . ' rev-parse HEAD'),
            SPC_SOURCE_LOCAL => 'LOCAL HASH IS ALWAYS DIFFERENT',
            default => filter_var(getenv('SPC_IGNORE_BAD_HASH'), FILTER_VALIDATE_BOOLEAN) ? '' : throw new SPCInternalException("Unknown source type: {$lock_options['source_type']}"),
        };
        if ($result === false && !filter_var(getenv('SPC_IGNORE_BAD_HASH'), FILTER_VALIDATE_BOOLEAN)) {
            throw new SPCInternalException("Failed to get hash for source: {$lock_options['source_type']}");
        }
        return $result ?: '';
    }

    /**
     * @param array  $lock_options Lock options
     * @param string $destination  Target directory
     */
    public static function putLockSourceHash(array $lock_options, string $destination): void
    {
        $hash = LockFile::getLockSourceHash($lock_options);
        if ($lock_options['source_type'] === SPC_SOURCE_LOCAL) {
            logger()->debug("Source [{$lock_options['dirname']}] is local, no hash will be written.");
            return;
        }
        FileSystem::writeFile("{$destination}/.spc-hash", $hash);
    }

    /**
     * Try to lock source with hash.
     *
     * @param string $name Source name
     * @param array{
     *     source_type: string,
     *     dirname?: ?string,
     *     filename?: ?string,
     *     move_path: ?string,
     *     lock_as: int
     * } $data Source data
     */
    public static function lockSource(string $name, array $data): void
    {
        // calculate hash
        $hash = LockFile::getLockSourceHash($data);
        $data['hash'] = $hash;
        self::put($name, $data);
    }

    private static function init(): void
    {
        if (self::$lock_file_content === null) {
            // Initialize the lock file content if it hasn't been loaded yet
            if (!file_exists(self::LOCK_FILE)) {
                logger()->debug('Lock file does not exist: ' . self::LOCK_FILE . ', initializing empty lock file.');
                self::$lock_file_content = [];
                file_put_contents(self::LOCK_FILE, json_encode(self::$lock_file_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $file_content = file_get_contents(self::LOCK_FILE);
                self::$lock_file_content = json_decode($file_content, true);
                if (self::$lock_file_content === null) {
                    throw new SPCInternalException('Failed to decode lock file: ' . self::LOCK_FILE);
                }
            }
        }
    }

    /**
     * Remove the lock file or directory if it exists.
     *
     * @param array $lock_options lock item options, must contain 'source_type', 'filename' or 'dirname'
     */
    private static function removeLockFileIfExists(array $lock_options): void
    {
        if ($lock_options['source_type'] === SPC_SOURCE_ARCHIVE) {
            $path = self::getLockFullPath($lock_options);
            if (file_exists($path)) {
                logger()->info('Removing file ' . $path);
                unlink($path);
            } else {
                logger()->debug("Lock file [{$lock_options['filename']}] not found, skip removing file.");
            }
        } else {
            $path = self::getLockFullPath($lock_options);
            if (is_dir($path)) {
                logger()->info('Removing directory ' . $path);
                FileSystem::removeDir($path);
            } else {
                logger()->debug("Lock directory [{$lock_options['dirname']}] not found, skip removing directory.");
            }
        }
    }
}
