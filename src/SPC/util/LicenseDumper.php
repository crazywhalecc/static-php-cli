<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\FileSystem;

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
     * @throws WrongUsageException
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function dump(string $target_dir): bool
    {
        // mkdir first
        if (is_dir($target_dir) && !FileSystem::removeDir($target_dir)) {
            logger()->warning('Target dump directory is noe empty, be aware!');
        }
        FileSystem::createDir($target_dir);
        foreach ($this->exts as $ext) {
            if (Config::getExt($ext, 'type') !== 'external') {
                continue;
            }
            $source_name = Config::getExt($ext, 'source');
            $content = $this->getSourceLicense($source_name);
            file_put_contents($target_dir . '/ext_' . $ext . '.txt', $content);
        }

        foreach ($this->libs as $lib) {
            $source_name = Config::getLib($lib, 'source');
            $content = $this->getSourceLicense($source_name);
            file_put_contents($target_dir . '/lib_' . $lib . '.txt', $content);
        }

        foreach ($this->sources as $source) {
            file_put_contents($target_dir . '/src_' . $source . '.txt', $this->getSourceLicense($source));
        }
        return true;
    }

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    private function getSourceLicense(string $source_name): ?string
    {
        $src = Config::getSource($source_name)['license'] ?? null;
        if ($src === null) {
            throw new RuntimeException('source [' . $source_name . '] license meta is not exist');
        }

        return match ($src['type']) {
            'text' => $src['text'],
            'file' => $this->loadSourceFile($source_name, $src['path'], Config::getSource($source_name)['path'] ?? null),
            default => throw new RuntimeException('source [' . $source_name . '] license type is not allowed'),
        };
    }

    /**
     * @throws RuntimeException
     */
    private function loadSourceFile(string $source_name, string $in_path, ?string $custom_base_path = null): string
    {
        if (!file_exists(SOURCE_PATH . '/' . ($custom_base_path ?? $source_name) . '/' . $in_path)) {
            throw new RuntimeException('source [' . $source_name . '] license file [' . $in_path . '] is not exist');
        }
        return file_get_contents(SOURCE_PATH . '/' . ($custom_base_path ?? $source_name) . '/' . $in_path);
    }
}
