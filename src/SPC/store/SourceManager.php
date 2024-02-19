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
    public static function initSource(?array $sources = null, ?array $libs = null, ?array $exts = null): void
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
            if (!isset($lock[$source])) {
                throw new WrongUsageException('Source [' . $source . '] not downloaded or not locked, you should download it first !');
            }

            // check source dir exist
            $check = $lock[$source]['move_path'] === null ? (SOURCE_PATH . '/' . $source) : (SOURCE_PATH . '/' . $lock[$source]['move_path']);
            if (!is_dir($check)) {
                logger()->debug('Extracting source [' . $source . '] to ' . $check . ' ...');
                FileSystem::extractSource($source, DOWNLOAD_PATH . '/' . ($lock[$source]['filename'] ?? $lock[$source]['dirname']), $lock[$source]['move_path']);
            } else {
                logger()->debug('Source [' . $source . '] already extracted in ' . $check . ', skip !');
            }
        }
    }
}
