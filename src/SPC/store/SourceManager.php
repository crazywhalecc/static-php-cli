<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\exception\WrongUsageException;

class SourceManager
{
    public static function initSource(?array $sources = null, ?array $libs = null, ?array $exts = null, bool $source_only = false): void
    {
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
            if ($source_only || LockFile::get($pre_built_name) === null) {
                if (LockFile::get($source) === null) {
                    throw new WrongUsageException("Source [{$source}] not downloaded or not locked, you should download it first !");
                }
                $lock_name = $source;
            } else {
                $lock_name = $pre_built_name;
            }

            $lock_content = LockFile::get($lock_name);

            // check source dir exist
            $check = LockFile::getExtractPath($lock_name, SOURCE_PATH . '/' . $source);
            // $check = $lock[$lock_name]['move_path'] === null ? (SOURCE_PATH . '/' . $source) : (SOURCE_PATH . '/' . $lock[$lock_name]['move_path']);
            if (!is_dir($check)) {
                logger()->debug("Extracting source [{$source}] to {$check} ...");
                $filename = LockFile::getLockFullPath($lock_content);
                FileSystem::extractSource($source, $lock_content['source_type'], $filename, $check);
                LockFile::putLockSourceHash($lock_content, $check);
                continue;
            }
            // if a lock file does not have hash, calculate with the current source (backward compatibility)
            if (!isset($lock_content['hash'])) {
                $hash = LockFile::getLockSourceHash($lock_content);
            } else {
                $hash = $lock_content['hash'];
            }

            // when source already extracted, detect if the extracted source hash is the same as the lock file one
            if (file_exists("{$check}/.spc-hash") && FileSystem::readFile("{$check}/.spc-hash") === $hash) {
                logger()->debug("Source [{$source}] already extracted in {$check}, skip !");
                continue;
            }

            // ext imap was included in php < 8.4 which we should not extract,
            // but since it's not simple to compare php version, for now we just skip it
            if ($source === 'ext-imap') {
                logger()->debug("Source [ext-imap] already extracted in {$check}, skip !");
                continue;
            }

            // if not, remove the source dir and extract again
            logger()->notice("Source [{$source}] hash mismatch, removing old source dir and extracting again ...");
            FileSystem::removeDir($check);
            $filename = LockFile::getLockFullPath($lock_content);
            $move_path = LockFile::getExtractPath($lock_name, SOURCE_PATH . '/' . $source);
            FileSystem::extractSource($source, $lock_content['source_type'], $filename, $move_path);
            LockFile::putLockSourceHash($lock_content, $check);
        }
    }
}
