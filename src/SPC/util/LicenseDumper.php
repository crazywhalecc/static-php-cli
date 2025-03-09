<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\FileSystem;

/**
 * License dumper, dump source license files to target directory
 */
class LicenseDumper
{
    private array $exts = [];

    private array $libs = [];

    private array $sources = [];

    public function addExts(array $exts): LicenseDumper
    {
        $this->exts = array_merge($exts, $this->exts);
        return $this;
    }

    public function addLibs(array $libs): LicenseDumper
    {
        $this->libs = array_merge($libs, $this->libs);
        return $this;
    }

    public function addSources(array $sources): LicenseDumper
    {
        $this->sources = array_merge($sources, $this->sources);
        return $this;
    }

    /**
     * Dump source licenses to target directory
     *
     * @param  string              $target_dir Target directory
     * @return bool                Success or not
     * @throws WrongUsageException
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function dump(string $target_dir): bool
    {
        // mkdir first
        if (is_dir($target_dir) && !FileSystem::removeDir($target_dir)) {
            logger()->warning('Target dump directory is not empty, be aware!');
        }
        FileSystem::createDir($target_dir);
        foreach ($this->exts as $ext) {
            if (Config::getExt($ext, 'type') !== 'external') {
                continue;
            }

            $source_name = Config::getExt($ext, 'source');
            foreach ($this->getSourceLicenses($source_name) as $index => $license) {
                $result = file_put_contents("{$target_dir}/ext_{$ext}_{$index}.txt", $license);
                if ($result === false) {
                    return false;
                }
            }
        }

        foreach ($this->libs as $lib) {
            if (Config::getLib($lib, 'type', 'lib') !== 'lib') {
                continue;
            }
            $source_name = Config::getLib($lib, 'source');
            foreach ($this->getSourceLicenses($source_name) as $index => $license) {
                $result = file_put_contents("{$target_dir}/lib_{$lib}_{$index}.txt", $license);
                if ($result === false) {
                    return false;
                }
            }
        }

        foreach ($this->sources as $source) {
            foreach ($this->getSourceLicenses($source) as $index => $license) {
                $result = file_put_contents("{$target_dir}/src_{$source}_{$index}.txt", $license);
                if ($result === false) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @return string[]
     * @throws FileSystemException
     * @throws RuntimeException
     */
    private function getSourceLicenses(string $source_name): iterable
    {
        $licenses = Config::getSource($source_name)['license'] ?? [];
        if ($licenses === []) {
            throw new RuntimeException('source [' . $source_name . '] license meta not exist');
        }

        if (!array_is_list($licenses)) {
            $licenses = [$licenses];
        }

        foreach ($licenses as $index => $license) {
            yield $index => match ($license['type']) {
                'text' => $license['text'],
                'file' => $this->loadSourceFile($source_name, $index, $license['path'], Config::getSource($source_name)['path'] ?? null),
                default => throw new RuntimeException('source [' . $source_name . '] license type is not allowed'),
            };
        }
    }

    /**
     * @throws RuntimeException
     */
    private function loadSourceFile(string $source_name, int $index, null|array|string $in_path, ?string $custom_base_path = null): string
    {
        if (is_null($in_path)) {
            throw new RuntimeException('source [' . $source_name . '] license file is not set, please check config/source.json');
        }

        if (!is_array($in_path)) {
            $in_path = [$in_path];
        }

        foreach ($in_path as $item) {
            if (file_exists(SOURCE_PATH . '/' . ($custom_base_path ?? $source_name) . '/' . $item)) {
                return file_get_contents(SOURCE_PATH . '/' . ($custom_base_path ?? $source_name) . '/' . $item);
            }
        }

        if (file_exists(BUILD_ROOT_PATH . '/source-licenses/' . $source_name . '/' . $index . '.txt')) {
            return file_get_contents(BUILD_ROOT_PATH . '/source-licenses/' . $source_name . '/' . $index . '.txt');
        }

        throw new RuntimeException('Cannot find any license file in source [' . $source_name . '] directory!');
    }
}
