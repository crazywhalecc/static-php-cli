<?php

declare(strict_types=1);

namespace StaticPHP\Artifact;

use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\FileSystemException;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\Package;
use StaticPHP\Registry\ArtifactLoader;
use StaticPHP\Runtime\Shell\Shell;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use StaticPHP\Util\V2CompatLayer;

/**
 * ArtifactExtractor is responsible for extracting downloaded artifacts to their target locations.
 *
 * Extraction rules for source:
 * 1. If extract is not specified: SOURCE_PATH/{artifact_name}
 * 2. If extract is relative path: SOURCE_PATH/{value}
 * 3. If extract is absolute path: {value}
 * 4. If extract is array (dict): selective extraction (file mapping)
 *
 * Extraction rules for binary:
 * 1. If extract is not specified: PKG_ROOT_PATH (standard mode)
 * 2. If extract is "hosted": BUILD_ROOT_PATH (standard mode, for pre-built libraries)
 * 3. If extract is relative path: PKG_ROOT_PATH/{value} (standard mode)
 * 4. If extract is absolute path: {value} (standard mode)
 * 5. If extract is array (dict): selective extraction mode
 */
class ArtifactExtractor
{
    /** @var array<string, bool> Track extracted artifacts to avoid duplicate extraction */
    protected array $extracted = [];

    public function __construct(
        protected ArtifactCache $cache,
        protected bool $interactive = true
    ) {}

    /**
     * Extract all artifacts for a list of packages.
     *
     * @param array<Package> $packages     Packages to extract artifacts for
     * @param bool           $force_source If true, always extract source (ignore binary)
     */
    public function extractForPackages(array $packages, bool $force_source = false): void
    {
        // Collect all unique artifacts
        $artifacts = [];
        foreach ($packages as $package) {
            $artifact = $package->getArtifact();
            if ($artifact !== null && !isset($artifacts[$artifact->getName()])) {
                $artifacts[$artifact->getName()] = $artifact;
            }
        }

        // Sort: php-src should be extracted first (extensions depend on it)
        uksort($artifacts, function (string $a, string $b): int {
            if ($a === 'php-src') {
                return -1;
            }
            if ($b === 'php-src') {
                return 1;
            }
            return 0;
        });

        // Extract each artifact
        foreach ($artifacts as $artifact) {
            $this->extract($artifact, $force_source);
        }
    }

    /**
     * Extract a single artifact.
     *
     * @param Artifact|string $artifact     The artifact to extract
     * @param bool            $force_source If true, always extract source (ignore binary)
     */
    public function extract(Artifact|string $artifact, bool $force_source = false): int
    {
        if (is_string($artifact)) {
            $name = $artifact;
            $artifact = ArtifactLoader::getArtifactInstance($name);
        } else {
            $name = $artifact->getName();
        }

        // Already extracted in this session
        if (isset($this->extracted[$name])) {
            logger()->debug("Artifact [{$name}] already extracted in this session, skip.");
            return SPC_STATUS_ALREADY_EXTRACTED;
        }

        // Determine: use binary or source?
        $use_binary = !$force_source && $artifact->shouldUseBinary();

        if ($this->interactive) {
            Shell::passthruCallback(function () {
                InteractiveTerm::advance();
            });
        }

        try {
            V2CompatLayer::beforeExtractHook($artifact);
            if ($use_binary) {
                $status = $this->extractBinary($artifact);
            } else {
                $status = $this->extractSource($artifact);
            }
            V2CompatLayer::afterExtractHook($artifact);
        } finally {
            if ($this->interactive) {
                Shell::passthruCallback(null);
            }
        }

        $this->extracted[$name] = true;
        return $status;
    }

    /**
     * Extract source artifact.
     */
    protected function extractSource(Artifact $artifact): int
    {
        $name = $artifact->getName();
        $cache_info = $this->cache->getSourceInfo($name);

        if ($cache_info === null) {
            throw new WrongUsageException("Artifact source [{$name}] not downloaded, please download it first!");
        }

        $source_file = $this->cache->getCacheFullPath($cache_info);
        $target_path = $artifact->getSourceDir();

        // Check for custom extract callback
        if ($artifact->hasSourceExtractCallback()) {
            logger()->info("Extracting source [{$name}] using custom callback...");
            $callback = $artifact->getSourceExtractCallback();
            ApplicationContext::invoke($callback, [
                Artifact::class => $artifact,
                'source_file' => $source_file,
                'target_path' => $target_path,
            ]);
            // Emit after hooks
            $artifact->emitAfterSourceExtract($target_path);
            logger()->debug("Emitted after-source-extract hooks for [{$name}]");
            return SPC_STATUS_EXTRACTED;
        }

        // Check for selective extraction (dict mode)
        $extract_config = $artifact->getDownloadConfig('source')['extract'] ?? null;
        if (is_array($extract_config)) {
            $this->doSelectiveExtract($name, $cache_info, $extract_config);
            $artifact->emitAfterSourceExtract($target_path);
            logger()->debug("Emitted after-source-extract hooks for [{$name}]");
            return SPC_STATUS_EXTRACTED;
        }

        // Standard extraction
        $hash = $cache_info['hash'] ?? null;

        if ($this->isAlreadyExtracted($target_path, $hash)) {
            logger()->debug("Source [{$name}] already extracted at {$target_path}, skip.");
            return SPC_STATUS_ALREADY_EXTRACTED;
        }

        // Remove old directory if hash mismatch
        if (is_dir($target_path)) {
            logger()->notice("Source [{$name}] hash mismatch, re-extracting...");
            FileSystem::removeDir($target_path);
        }

        logger()->info("Extracting source [{$name}] to {$target_path}...");
        $this->doStandardExtract($name, $cache_info, $target_path);

        // Emit after hooks
        $artifact->emitAfterSourceExtract($target_path);
        logger()->debug("Emitted after-source-extract hooks for [{$name}]");

        // Write hash marker
        if ($hash !== null) {
            FileSystem::writeFile("{$target_path}/.spc-hash", $hash);
        }
        return SPC_STATUS_EXTRACTED;
    }

    /**
     * Extract binary artifact.
     */
    protected function extractBinary(Artifact $artifact): int
    {
        $name = $artifact->getName();
        $platform = SystemTarget::getCurrentPlatformString();
        $cache_info = $this->cache->getBinaryInfo($name, $platform);

        if ($cache_info === null) {
            throw new WrongUsageException("Artifact binary [{$name}] for platform [{$platform}] not downloaded!");
        }

        $source_file = $this->cache->getCacheFullPath($cache_info);
        $extract_config = $artifact->getBinaryExtractConfig($cache_info);
        $target_path = $extract_config['path'];

        // Check for custom extract callback
        if ($artifact->hasBinaryExtractCallback()) {
            logger()->info("Extracting binary [{$name}] using custom callback...");
            $callback = $artifact->getBinaryExtractCallback();
            ApplicationContext::invoke($callback, [
                Artifact::class => $artifact,
                'source_file' => $source_file,
                'target_path' => $target_path,
                'platform' => $platform,
            ]);
            // Emit after hooks
            $artifact->emitAfterBinaryExtract($target_path, $platform);
            logger()->debug("Emitted after-binary-extract hooks for [{$name}]");
            return SPC_STATUS_EXTRACTED;
        }

        // Handle different extraction modes
        $mode = $extract_config['mode'];

        if ($mode === 'selective') {
            $this->doSelectiveExtract($name, $cache_info, $extract_config['files']);
            $artifact->emitAfterBinaryExtract($target_path, $platform);
            logger()->debug("Emitted after-binary-extract hooks for [{$name}]");
            return SPC_STATUS_EXTRACTED;
        }

        $hash = $cache_info['hash'] ?? null;

        if ($this->isAlreadyExtracted($target_path, $hash)) {
            logger()->debug("Binary [{$name}] already extracted at {$target_path}, skip.");
            return SPC_STATUS_ALREADY_EXTRACTED;
        }

        logger()->info("Extracting binary [{$name}] to {$target_path}...");
        $this->doStandardExtract($name, $cache_info, $target_path);

        $artifact->emitAfterBinaryExtract($target_path, $platform);
        logger()->debug("Emitted after-binary-extract hooks for [{$name}]");

        if ($hash !== null && $cache_info['cache_type'] !== 'file') {
            FileSystem::writeFile("{$target_path}/.spc-hash", $hash);
        }
        return SPC_STATUS_EXTRACTED;
    }

    /**
     * Standard extraction: extract entire archive to target directory.
     */
    protected function doStandardExtract(string $name, array $cache_info, string $target_path): void
    {
        $source_file = $this->cache->getCacheFullPath($cache_info);
        $cache_type = $cache_info['cache_type'];

        // Validate source file exists before extraction
        $this->validateSourceFile($name, $source_file, $cache_type);

        $this->extractWithType($cache_type, $source_file, $target_path);
    }

    /**
     * Selective extraction: extract specific files to specific locations.
     *
     * @param string               $name       Artifact name
     * @param array                $cache_info Cache info
     * @param array<string,string> $file_map   Map of source path => destination path
     */
    protected function doSelectiveExtract(string $name, array $cache_info, array $file_map): void
    {
        // Extract to temp directory first
        $temp_path = sys_get_temp_dir() . '/spc_extract_' . $name . '_' . bin2hex(random_bytes(8));

        try {
            logger()->info("Extracting [{$name}] with selective file mapping...");

            $source_file = $this->cache->getCacheFullPath($cache_info);
            $cache_type = $cache_info['cache_type'];

            // Validate source file exists before extraction
            $this->validateSourceFile($name, $source_file, $cache_type);

            $this->extractWithType($cache_type, $source_file, $temp_path);

            // Process file mappings
            foreach ($file_map as $src_pattern => $dst_path) {
                $dst_path = $this->replacePathVariables($dst_path);
                $src_full = "{$temp_path}/{$src_pattern}";

                // Handle glob patterns
                if (str_contains($src_pattern, '*')) {
                    $matches = glob($src_full);
                    if (empty($matches)) {
                        logger()->warning("No files matched pattern [{$src_pattern}] in [{$name}]");
                        continue;
                    }
                    foreach ($matches as $match) {
                        $filename = basename($match);
                        $target = rtrim($dst_path, '/') . '/' . $filename;
                        $this->copyFileOrDir($match, $target);
                    }
                } else {
                    // Direct file/directory copy
                    if (!file_exists($src_full) && !is_dir($src_full)) {
                        logger()->warning("Source [{$src_pattern}] not found in [{$name}]");
                        continue;
                    }
                    $this->copyFileOrDir($src_full, $dst_path);
                }
            }
        } finally {
            // Cleanup temp directory
            if (is_dir($temp_path)) {
                FileSystem::removeDir($temp_path);
            }
        }
    }

    /**
     * Check if artifact is already extracted with correct hash.
     */
    protected function isAlreadyExtracted(string $path, ?string $expected_hash): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        // Local source: always re-extract
        if ($expected_hash === null) {
            return false;
        }

        $hash_file = "{$path}/.spc-hash";
        if (!file_exists($hash_file)) {
            return false;
        }

        return FileSystem::readFile($hash_file) === $expected_hash;
    }

    /**
     * Validate that the source file/directory exists before extraction.
     *
     * @param string $name        Artifact name (for error messages)
     * @param string $source_file Path to the source file or directory
     * @param string $cache_type  Cache type: archive, git, local
     */
    protected function validateSourceFile(string $name, string $source_file, string $cache_type): void
    {
        $converted_path = FileSystem::convertPath($source_file);

        switch ($cache_type) {
            case 'archive':
                if (!file_exists($converted_path)) {
                    throw new WrongUsageException(
                        "Artifact [{$name}] source archive not found at: {$converted_path}\n" .
                        "The file may have been deleted or moved. Please run 'spc download {$name}' to re-download it."
                    );
                }
                if (!is_file($converted_path)) {
                    throw new WrongUsageException(
                        "Artifact [{$name}] source path exists but is not a file: {$converted_path}\n" .
                        'Expected an archive file. Please check your downloads directory.'
                    );
                }
                break;
            case 'file':
                if (!file_exists($converted_path)) {
                    throw new WrongUsageException(
                        "Artifact [{$name}] source file not found at: {$converted_path}\n" .
                        "The file may have been deleted or moved. Please run 'spc download {$name}' to re-download it."
                    );
                }
                if (!is_file($converted_path)) {
                    throw new WrongUsageException(
                        "Artifact [{$name}] source path exists but is not a file: {$converted_path}\n" .
                        'Expected a regular file. Please check your downloads directory.'
                    );
                }
                break;
            case 'git':
                if (!is_dir($converted_path)) {
                    throw new WrongUsageException(
                        "Artifact [{$name}] git repository not found at: {$converted_path}\n" .
                        "The directory may have been deleted. Please run 'spc download {$name}' to re-clone it."
                    );
                }
                // Optionally check for .git directory to ensure it's a valid git repo
                if (!is_dir("{$converted_path}/.git")) {
                    logger()->warning("Artifact [{$name}] directory exists but may not be a valid git repository (missing .git)");
                }
                break;
            case 'local':
                if (!file_exists($converted_path) && !is_dir($converted_path)) {
                    throw new WrongUsageException(
                        "Artifact [{$name}] local source not found at: {$converted_path}\n" .
                        'Please ensure the local path is correct and accessible.'
                    );
                }
                break;
            default:
                throw new SPCInternalException("Unknown cache type: {$cache_type}");
        }

        logger()->debug("Validated source file for [{$name}]: {$converted_path} (type: {$cache_type})");
    }

    /**
     * Copy file or directory to destination.
     */
    protected function copyFileOrDir(string $src, string $dst): void
    {
        $dst_dir = dirname($dst);
        if (!is_dir($dst_dir)) {
            FileSystem::createDir($dst_dir);
        }

        if (is_dir($src)) {
            FileSystem::copyDir($src, $dst);
        } else {
            copy($src, $dst);
        }

        logger()->debug("Copied {$src} -> {$dst}");
    }

    /**
     * Extract source based on cache type.
     *
     * @param string $cache_type  Cache type: archive, git, local
     * @param string $source_file Path to source file or directory
     * @param string $target_path Target extraction path
     */
    protected function extractWithType(string $cache_type, string $source_file, string $target_path): void
    {
        match ($cache_type) {
            'archive' => $this->extractArchive($source_file, $target_path),
            'file' => $this->copyFile($source_file, $target_path),
            'git' => FileSystem::copyDir(FileSystem::convertPath($source_file), $target_path),
            'local' => symlink(FileSystem::convertPath($source_file), $target_path),
            default => throw new SPCInternalException("Unknown cache type: {$cache_type}"),
        };
    }

    /**
     * Extract archive file to target directory.
     *
     * Supports: tar, tar.gz, tgz, tar.bz2, tar.xz, txz, zip, exe
     */
    protected function extractArchive(string $filename, string $target): void
    {
        $target = FileSystem::convertPath($target);
        $filename = FileSystem::convertPath($filename);

        FileSystem::createDir($target);

        if (PHP_OS_FAMILY === 'Windows') {
            // Use 7za.exe for Windows
            $is_txz = str_ends_with($filename, '.txz') || str_ends_with($filename, '.tar.xz');
            default_shell()->execute7zExtract($filename, $target, $is_txz);
            return;
        }

        // Unix-like systems: determine compression type
        if (str_ends_with($filename, '.tar.gz') || str_ends_with($filename, '.tgz')) {
            default_shell()->executeTarExtract($filename, $target, 'gz');
        } elseif (str_ends_with($filename, '.tar.bz2')) {
            default_shell()->executeTarExtract($filename, $target, 'bz2');
        } elseif (str_ends_with($filename, '.tar.xz') || str_ends_with($filename, '.txz')) {
            default_shell()->executeTarExtract($filename, $target, 'xz');
        } elseif (str_ends_with($filename, '.tar')) {
            default_shell()->executeTarExtract($filename, $target, 'none');
        } elseif (str_ends_with($filename, '.zip')) {
            // Zip requires special handling for strip-components
            $this->unzipWithStrip($filename, $target);
        } elseif (str_ends_with($filename, '.exe')) {
            // exe just copy to target
            $dest_file = FileSystem::convertPath("{$target}/" . basename($filename));
            FileSystem::copy($filename, $dest_file);
        } else {
            throw new FileSystemException("Unknown archive format: {$filename}");
        }
    }

    /**
     * Unzip file with stripping top-level directory.
     */
    protected function unzipWithStrip(string $zip_file, string $extract_path): void
    {
        $temp_dir = FileSystem::convertPath(sys_get_temp_dir() . '/spc_unzip_' . bin2hex(random_bytes(16)));
        $zip_file = FileSystem::convertPath($zip_file);
        $extract_path = FileSystem::convertPath($extract_path);

        // Extract to temp dir
        FileSystem::createDir($temp_dir);

        if (PHP_OS_FAMILY === 'Windows') {
            default_shell()->execute7zExtract($zip_file, $temp_dir);
        } else {
            default_shell()->executeUnzip($zip_file, $temp_dir);
        }

        // Scan first level dirs (relative, not recursive, include dirs)
        $contents = FileSystem::scanDirFiles($temp_dir, false, true, true);
        if ($contents === false) {
            throw new FileSystemException('Cannot scan unzip temp dir: ' . $temp_dir);
        }

        // If extract path already exists, remove it
        if (is_dir($extract_path)) {
            FileSystem::removeDir($extract_path);
        }

        // If only one dir, move its contents to extract_path
        $subdir = FileSystem::convertPath("{$temp_dir}/{$contents[0]}");
        if (count($contents) === 1 && is_dir($subdir)) {
            $this->moveFileOrDir($subdir, $extract_path);
        } else {
            // Else, if it contains only one dir, strip dir and copy other files
            $dircount = 0;
            $dir = [];
            $top_files = [];
            foreach ($contents as $item) {
                if (is_dir(FileSystem::convertPath("{$temp_dir}/{$item}"))) {
                    ++$dircount;
                    $dir[] = $item;
                } else {
                    $top_files[] = $item;
                }
            }

            // Extract dir contents to extract_path
            FileSystem::createDir($extract_path);

            // Extract move dir
            if ($dircount === 1) {
                $sub_contents = FileSystem::scanDirFiles("{$temp_dir}/{$dir[0]}", false, true, true);
                if ($sub_contents === false) {
                    throw new FileSystemException("Cannot scan unzip temp sub-dir: {$dir[0]}");
                }
                foreach ($sub_contents as $sub_item) {
                    $this->moveFileOrDir(
                        FileSystem::convertPath("{$temp_dir}/{$dir[0]}/{$sub_item}"),
                        FileSystem::convertPath("{$extract_path}/{$sub_item}")
                    );
                }
            } else {
                foreach ($dir as $item) {
                    $this->moveFileOrDir(
                        FileSystem::convertPath("{$temp_dir}/{$item}"),
                        FileSystem::convertPath("{$extract_path}/{$item}")
                    );
                }
            }

            // Move top-level files to extract_path
            foreach ($top_files as $top_file) {
                $this->moveFileOrDir(
                    FileSystem::convertPath("{$temp_dir}/{$top_file}"),
                    FileSystem::convertPath("{$extract_path}/{$top_file}")
                );
            }
        }

        // Clean up temp directory
        FileSystem::removeDir($temp_dir);
    }

    /**
     * Replace path variables.
     */
    protected function replacePathVariables(string $path): string
    {
        $replacement = [
            '{pkg_root_path}' => PKG_ROOT_PATH,
            '{build_root_path}' => BUILD_ROOT_PATH,
            '{source_path}' => SOURCE_PATH,
            '{download_path}' => DOWNLOAD_PATH,
            '{working_dir}' => WORKING_DIR,
        ];
        return str_replace(array_keys($replacement), array_values($replacement), $path);
    }

    /**
     * Move file or directory, handling cross-device scenarios
     * Uses rename() if possible, falls back to copy+delete for cross-device moves
     *
     * @param string $source Source path
     * @param string $dest   Destination path
     */
    private static function moveFileOrDir(string $source, string $dest): void
    {
        $source = FileSystem::convertPath($source);
        $dest = FileSystem::convertPath($dest);

        // Check if source and dest are on the same device to avoid cross-device rename errors
        $source_stat = @stat($source);
        $dest_parent = dirname($dest);
        $dest_stat = @stat($dest_parent);

        // Only use rename if on same device
        if ($source_stat !== false && $dest_stat !== false && $source_stat['dev'] === $dest_stat['dev']) {
            if (@rename($source, $dest)) {
                return;
            }
        }

        // Fall back to copy + delete for cross-device moves or if rename failed
        if (is_dir($source)) {
            FileSystem::copyDir($source, $dest);
            FileSystem::removeDir($source);
        } else {
            if (!copy($source, $dest)) {
                throw new FileSystemException("Failed to copy file from {$source} to {$dest}");
            }
            if (!unlink($source)) {
                throw new FileSystemException("Failed to remove source file: {$source}");
            }
        }
    }

    private function copyFile(string $source_file, string $target_path): void
    {
        FileSystem::createDir(dirname($target_path));
        FileSystem::copy(FileSystem::convertPath($source_file), $target_path);
    }
}
