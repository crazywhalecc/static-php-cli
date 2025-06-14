<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;

class SourceManager
{
    /**
     * @throws WrongUsageException
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public static function initSource(?array $sources = null, ?array $libs = null, ?array $exts = null, bool $source_only = false): void
    {
        if (!file_exists(DOWNLOAD_PATH . '/.lock.json')) {
            throw new WrongUsageException('Download lock file "downloads/.lock.json" not found, maybe you need to download sources first ?');
        }
        $lock = json_decode(FileSystem::readFile(DOWNLOAD_PATH . '/.lock.json'), true);

        $sources_extracted = [];
        // source check exist
        if (is_array($sources)) {
            foreach ($sources as $source) {
                $sources_extracted[$source] = true;
            }
        }
        // lib check source exist
        if (is_array($libs)) {
            foreach ($libs as $lib) {
                // get source name for lib
                $source = Config::getLib($lib, 'source');
                $sources_extracted[$source] = true;
            }
        }
        // ext check source exist
        if (is_array($exts)) {
            foreach ($exts as $ext) {
                // get source name for ext
                if (Config::getExt($ext, 'type') !== 'external') {
                    continue;
                }
                $source = Config::getExt($ext, 'source');
                $sources_extracted[$source] = true;
            }
        }

        // start check
        foreach ($sources_extracted as $source => $item) {
            if (Config::getSource($source) === null) {
                throw new WrongUsageException("Source [{$source}] does not exist, please check the name and correct it !");
            }
            // check source downloaded
            $pre_built_name = Downloader::getPreBuiltLockName($source);
            if ($source_only || !isset($lock[$pre_built_name])) {
                if (!isset($lock[$source])) {
                    throw new WrongUsageException("Source [{$source}] not downloaded or not locked, you should download it first !");
                }
                $lock_name = $source;
            } else {
                $lock_name = $pre_built_name;
            }

            // check source dir exist
            $check = $lock[$lock_name]['move_path'] === null ? (SOURCE_PATH . '/' . $source) : (SOURCE_PATH . '/' . $lock[$lock_name]['move_path']);
            if (!is_dir($check)) {
                logger()->debug('Extracting source [' . $source . '] to ' . $check . ' ...');
                $filename = self::getSourceFullPath($lock[$lock_name]);
                FileSystem::extractSource($source, $lock[$lock_name]['source_type'], $filename, $lock[$lock_name]['move_path']);
                Downloader::putLockSourceHash($lock[$lock_name], $check);
                continue;
            }
            // if a lock file does not have hash, calculate with the current source (backward compatibility)
            if (!isset($lock[$lock_name]['hash'])) {
                $hash = Downloader::getLockSourceHash($lock[$lock_name]);
            } else {
                $hash = $lock[$lock_name]['hash'];
            }

            // when source already extracted, detect if the extracted source hash is the same as the lock file one
            if (file_exists("{$check}/.spc-hash") && FileSystem::readFile("{$check}/.spc-hash") === $hash) {
                logger()->debug('Source [' . $source . '] already extracted in ' . $check . ', skip !');
                continue;
            }

            // if not, remove the source dir and extract again
            logger()->notice("Source [{$source}] hash mismatch, removing old source dir and extracting again ...");
            FileSystem::removeDir($check);
            $filename = self::getSourceFullPath($lock[$lock_name]);
            FileSystem::extractSource($source, $lock[$lock_name]['source_type'], $filename, $lock[$lock_name]['move_path']);
            Downloader::putLockSourceHash($lock[$lock_name], $check);
        }
    }

    private static function getSourceFullPath(array $lock_options): string
    {
        return match ($lock_options['source_type']) {
            SPC_SOURCE_ARCHIVE => FileSystem::isRelativePath($lock_options['filename']) ? (DOWNLOAD_PATH . '/' . $lock_options['filename']) : $lock_options['filename'],
            SPC_SOURCE_GIT, SPC_SOURCE_LOCAL => FileSystem::isRelativePath($lock_options['dirname']) ? (DOWNLOAD_PATH . '/' . $lock_options['dirname']) : $lock_options['dirname'],
            default => throw new WrongUsageException("Unknown source type: {$lock_options['source_type']}"),
        };
    }
}
