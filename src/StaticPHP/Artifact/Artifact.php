<?php

declare(strict_types=1);

namespace StaticPHP\Artifact;

use StaticPHP\Config\ArtifactConfig;
use StaticPHP\Config\ConfigValidator;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

class Artifact
{
    public const int FETCH_PREFER_SOURCE = 0;

    public const int FETCH_PREFER_BINARY = 1;

    public const int FETCH_ONLY_SOURCE = 2;

    public const int FETCH_ONLY_BINARY = 3;

    protected ?array $config;

    /** @var null|callable Bind custom source fetcher callback */
    protected mixed $custom_source_callback = null;

    /** @var array<string, callable> Bind custom binary fetcher callbacks */
    protected mixed $custom_binary_callbacks = [];

    /** @var null|callable Bind custom source extract callback (completely takes over extraction) */
    protected mixed $source_extract_callback = null;

    /** @var null|array{callback: callable, platforms: string[]} Bind custom binary extract callback (completely takes over extraction) */
    protected ?array $binary_extract_callback = null;

    /** @var array<callable> After source extract hooks */
    protected array $after_source_extract_callbacks = [];

    /** @var array<array{callback: callable, platforms: string[]}> After binary extract hooks */
    protected array $after_binary_extract_callbacks = [];

    public function __construct(protected readonly string $name, ?array $config = null)
    {
        $this->config = $config ?? ArtifactConfig::get($name);
        if ($this->config === null) {
            throw new WrongUsageException("Artifact '{$name}' not found.");
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Checks if the source of an artifact is already downloaded.
     *
     * @param bool $compare_hash Whether to compare hash of the downloaded source
     */
    public function isSourceDownloaded(bool $compare_hash = false): bool
    {
        return ApplicationContext::get(ArtifactCache::class)->isSourceDownloaded($this->name, $compare_hash);
    }

    /**
     * Checks if the binary of an artifact is already downloaded for the specified target OS.
     *
     * @param null|string $target_os    Target OS platform string, null for current platform
     * @param bool        $compare_hash Whether to compare hash of the downloaded binary
     */
    public function isBinaryDownloaded(?string $target_os = null, bool $compare_hash = false): bool
    {
        $target_os = $target_os ?? SystemTarget::getCurrentPlatformString();
        return ApplicationContext::get(ArtifactCache::class)->isBinaryDownloaded($this->name, $target_os, $compare_hash);
    }

    public function shouldUseBinary(): bool
    {
        $platform = SystemTarget::getCurrentPlatformString();
        return $this->isBinaryDownloaded($platform) && $this->hasPlatformBinary();
    }

    /**
     * Checks if the source of an artifact is already extracted.
     *
     * @param bool $compare_hash Whether to compare hash of the extracted source
     */
    public function isSourceExtracted(bool $compare_hash = false): bool
    {
        $target_path = $this->getSourceDir();

        if (!is_dir($target_path)) {
            return false;
        }

        if (!$compare_hash) {
            return true;
        }

        // Get expected hash from cache
        $cache_info = ApplicationContext::get(ArtifactCache::class)->getSourceInfo($this->name);
        if ($cache_info === null) {
            return false;
        }

        $expected_hash = $cache_info['hash'] ?? null;

        // Local source: always consider extracted if directory exists
        if ($expected_hash === null) {
            return true;
        }

        // Check hash marker file
        $hash_file = "{$target_path}/.spc-hash";
        if (!file_exists($hash_file)) {
            return false;
        }

        return FileSystem::readFile($hash_file) === $expected_hash;
    }

    /**
     * Checks if the binary of an artifact is already extracted for the specified target OS.
     *
     * @param null|string $target_os    Target OS platform string, null for current platform
     * @param bool        $compare_hash Whether to compare hash of the extracted binary
     */
    public function isBinaryExtracted(?string $target_os = null, bool $compare_hash = false): bool
    {
        $target_os = $target_os ?? SystemTarget::getCurrentPlatformString();
        // Get cache info first for custom binary support (extract path may be stored in cache)
        $cache_info = ApplicationContext::get(ArtifactCache::class)->getBinaryInfo($this->name, $target_os);
        $extract_config = $this->getBinaryExtractConfig($cache_info ?? []);
        $mode = $extract_config['mode'];

        // For merge mode, check marker file
        if ($mode === 'merge') {
            $target_path = $extract_config['path'];
            $marker_file = "{$target_path}/.spc-{$this->name}-installed";

            if (!file_exists($marker_file)) {
                return false;
            }

            if (!$compare_hash) {
                return true;
            }

            // Get expected hash from cache
            $cache_info = ApplicationContext::get(ArtifactCache::class)->getBinaryInfo($this->name, $target_os);
            if ($cache_info === null) {
                return false;
            }

            $expected_hash = $cache_info['hash'] ?? null;
            if ($expected_hash === null) {
                return true; // Local binary
            }

            $installed_hash = FileSystem::readFile($marker_file);
            return $installed_hash === $expected_hash;
        }

        // For selective mode, cannot reliably check extraction status
        if ($mode === 'selective') {
            // check files existence
            foreach ($extract_config['files'] as $target_file) {
                $target_file = FileSystem::replacePathVariable($target_file);
                if (!file_exists($target_file)) {
                    return false;
                }
            }
            return true;
        }

        // For standalone mode, check directory or file and hash
        $target_path = $extract_config['path'];

        // Check if target is a file or directory
        $is_file_target = !is_dir($target_path) && (pathinfo($target_path, PATHINFO_EXTENSION) !== '');

        if ($is_file_target) {
            // For single file extraction (e.g., vswhere.exe)
            if (!file_exists($target_path)) {
                return false;
            }
        } else {
            // For directory extraction
            if (!is_dir($target_path)) {
                return false;
            }
        }

        if (!$compare_hash) {
            return true;
        }

        // Get expected hash from cache
        $cache_info = ApplicationContext::get(ArtifactCache::class)->getBinaryInfo($this->name, $target_os);
        if ($cache_info === null) {
            return false;
        }

        $expected_hash = $cache_info['hash'] ?? null;

        // Local binary: always consider extracted if directory exists
        if ($expected_hash === null) {
            return true;
        }

        // Check hash marker file
        $hash_file = "{$target_path}/.spc-hash";
        if (!file_exists($hash_file)) {
            return false;
        }

        return FileSystem::readFile($hash_file) === $expected_hash;
    }

    /**
     * Checks if the artifact has a source defined.
     */
    public function hasSource(): bool
    {
        return isset($this->config['source']) || $this->custom_source_callback !== null;
    }

    /**
     * Checks if the artifact has a local binary defined for the current system target.
     */
    public function hasPlatformBinary(): bool
    {
        $target = SystemTarget::getCurrentPlatformString();
        return isset($this->config['binary'][$target]) || isset($this->custom_binary_callbacks[$target]);
    }

    /**
     * Get all platform strings for which a binary is declared (config or custom callback).
     *
     * For platforms where the binary type is "custom", a registered custom_binary_callback
     * is required to consider it truly installable.
     *
     * @return string[] e.g. ['linux-x86_64', 'linux-aarch64', 'macos-aarch64']
     */
    public function getBinaryPlatforms(): array
    {
        $platforms = [];
        if (isset($this->config['binary']) && is_array($this->config['binary'])) {
            foreach ($this->config['binary'] as $platform => $platformConfig) {
                $type = is_array($platformConfig) ? ($platformConfig['type'] ?? '') : '';
                if ($type === 'custom') {
                    // Only installable if a custom callback has been registered
                    if (isset($this->custom_binary_callbacks[$platform])) {
                        $platforms[] = $platform;
                    }
                } else {
                    $platforms[] = $platform;
                }
            }
        }
        // Include custom callbacks for platforms not listed in config at all
        foreach (array_keys($this->custom_binary_callbacks) as $platform) {
            if (!in_array($platform, $platforms, true)) {
                $platforms[] = $platform;
            }
        }
        return $platforms;
    }

    public function getDownloadConfig(string $type): mixed
    {
        return $this->config[$type] ?? null;
    }

    /**
     * Get source extraction directory.
     *
     * Rules:
     * 1. If extract is not specified: SOURCE_PATH/{artifact_name}
     * 2. If extract is relative path: SOURCE_PATH/{value}
     * 3. If extract is absolute path: {value}
     * 4. If extract is array (dict): handled by extractor (selective extraction)
     */
    public function getSourceDir(): string
    {
        // defined in config
        $extract = $this->config['source']['extract'] ?? null;

        if ($extract === null) {
            return FileSystem::convertPath(SOURCE_PATH . '/' . $this->name);
        }

        // Array (dict) mode - return default path, actual handling is in extractor
        if (is_array($extract)) {
            return FileSystem::convertPath(SOURCE_PATH . '/' . $this->name);
        }

        // String path
        $path = $this->replaceExtractPathVariables($extract);

        // Absolute path
        if (!FileSystem::isRelativePath($path)) {
            return FileSystem::convertPath($path);
        }

        // Relative path: based on SOURCE_PATH
        return FileSystem::convertPath(SOURCE_PATH . '/' . $path);
    }

    /**
     * Get source build root directory.
     * It's only worked when 'source-root' is defined in artifact config.
     * Normally it's equal to source dir.
     */
    public function getSourceRoot(): string
    {
        if (isset($this->config['metadata']['source-root'])) {
            return $this->getSourceDir() . '/' . ltrim($this->config['metadata']['source-root'], '/');
        }
        return $this->getSourceDir();
    }

    /**
     * Get binary extraction directory and mode.
     *
     * Rules:
     * 1. If extract is not specified: PKG_ROOT_PATH (standard mode)
     * 2. If extract is "hosted": BUILD_ROOT_PATH (standard mode, for pre-built libraries)
     * 3. If extract is relative path: PKG_ROOT_PATH/{value} (standard mode)
     * 4. If extract is absolute path: {value} (standard mode)
     * 5. If extract is array (dict): selective extraction mode
     *
     * @return array{path: ?string, mode: 'merge'|'selective'|'standard', files?: array}
     */
    public function getBinaryExtractConfig(array $cache_info = []): array
    {
        if (is_string($cache_info['extract'] ?? null)) {
            return ['path' => $this->replaceExtractPathVariables($cache_info['extract']), 'mode' => 'standard'];
        }

        $platform = SystemTarget::getCurrentPlatformString();
        $binary_config = $this->config['binary'][$platform] ?? null;

        if ($binary_config === null) {
            return ['path' => PKG_ROOT_PATH, 'mode' => 'standard'];
        }

        $extract = $binary_config['extract'] ?? null;

        // Not specified: PKG_ROOT_PATH merge
        if ($extract === null) {
            return ['path' => PKG_ROOT_PATH, 'mode' => 'standard'];
        }

        // "hosted" mode: BUILD_ROOT_PATH merge (for pre-built libraries)
        if ($extract === 'hosted' || ($binary_config['type'] ?? '') === 'hosted') {
            return ['path' => BUILD_ROOT_PATH, 'mode' => 'standard'];
        }

        // Array (dict) mode: selective extraction
        if (is_array($extract)) {
            return [
                'path' => null,
                'mode' => 'selective',
                'files' => $extract,
            ];
        }

        // String path
        $path = $this->replaceExtractPathVariables($extract);

        // Absolute path: standalone mode
        if (!FileSystem::isRelativePath($path)) {
            return ['path' => FileSystem::convertPath($path), 'mode' => 'standard'];
        }

        // Relative path: PKG_ROOT_PATH/{value} standalone mode
        return ['path' => FileSystem::convertPath(PKG_ROOT_PATH . '/' . $path), 'mode' => 'standard'];
    }

    /**
     * Get the binary extraction directory.
     * For merge mode, returns the base path.
     * For standalone mode, returns the specific directory.
     */
    public function getBinaryDir(): ?string
    {
        $config = $this->getBinaryExtractConfig();
        return $config['path'];
    }

    /**
     * Set custom source fetcher callback.
     */
    public function setCustomSourceCallback(callable $callback): void
    {
        $this->custom_source_callback = $callback;
    }

    public function getCustomSourceCallback(): ?callable
    {
        return $this->custom_source_callback ?? null;
    }

    public function getCustomBinaryCallback(): ?callable
    {
        $current_platform = SystemTarget::getCurrentPlatformString();
        return $this->custom_binary_callbacks[$current_platform] ?? null;
    }

    public function emitCustomBinary(): void
    {
        $current_platform = SystemTarget::getCurrentPlatformString();
        if (!isset($this->custom_binary_callbacks[$current_platform])) {
            throw new SPCInternalException("No custom binary callback defined for artifact '{$this->name}' on target OS '{$current_platform}'.");
        }
        $callback = $this->custom_binary_callbacks[$current_platform];
        ApplicationContext::invoke($callback, [Artifact::class => $this]);
    }

    /**
     * Set custom binary fetcher callback for a specific target OS.
     *
     * @param string   $target_os Target OS platform string (e.g. linux-x86_64)
     * @param callable $callback  Custom binary fetcher callback
     */
    public function setCustomBinaryCallback(string $target_os, callable $callback): void
    {
        ConfigValidator::validatePlatformString($target_os);
        $this->custom_binary_callbacks[$target_os] = $callback;
    }

    // ==================== Extraction Callbacks ====================

    /**
     * Set custom source extract callback.
     * This callback completely takes over the source extraction process.
     *
     * Callback signature: function(Artifact $artifact, string $source_file, string $target_path): void
     */
    public function setSourceExtractCallback(callable $callback): void
    {
        $this->source_extract_callback = $callback;
    }

    /**
     * Get the source extract callback.
     */
    public function getSourceExtractCallback(): ?callable
    {
        return $this->source_extract_callback;
    }

    /**
     * Check if a custom source extract callback is set.
     */
    public function hasSourceExtractCallback(): bool
    {
        return $this->source_extract_callback !== null;
    }

    /**
     * Set custom binary extract callback.
     * This callback completely takes over the binary extraction process.
     *
     * Callback signature: function(Artifact $artifact, string $source_file, string $target_path, string $platform): void
     *
     * @param callable $callback  The callback function
     * @param string[] $platforms Platform filters (empty = all platforms)
     */
    public function setBinaryExtractCallback(callable $callback, array $platforms = []): void
    {
        $this->binary_extract_callback = [
            'callback' => $callback,
            'platforms' => $platforms,
        ];
    }

    /**
     * Get the binary extract callback for current platform.
     *
     * @return null|callable The callback if set and matches current platform, null otherwise
     */
    public function getBinaryExtractCallback(): ?callable
    {
        if ($this->binary_extract_callback === null) {
            return null;
        }

        $platforms = $this->binary_extract_callback['platforms'];
        $current_platform = SystemTarget::getCurrentPlatformString();

        // Empty platforms array means all platforms
        if (empty($platforms) || in_array($current_platform, $platforms, true)) {
            return $this->binary_extract_callback['callback'];
        }

        return null;
    }

    /**
     * Check if a custom binary extract callback is set for current platform.
     */
    public function hasBinaryExtractCallback(): bool
    {
        return $this->getBinaryExtractCallback() !== null;
    }

    /**
     * Add a callback to run after source extraction completes.
     *
     * Callback signature: function(string $target_path): void
     */
    public function addAfterSourceExtractCallback(callable $callback): void
    {
        $this->after_source_extract_callbacks[] = $callback;
    }

    /**
     * Add a callback to run after binary extraction completes.
     *
     * Callback signature: function(string $target_path, string $platform): void
     *
     * @param callable $callback  The callback function
     * @param string[] $platforms Platform filters (empty = all platforms)
     */
    public function addAfterBinaryExtractCallback(callable $callback, array $platforms = []): void
    {
        $this->after_binary_extract_callbacks[] = [
            'callback' => $callback,
            'platforms' => $platforms,
        ];
    }

    /**
     * Emit all after source extract callbacks.
     *
     * @param string $target_path The directory where source was extracted
     */
    public function emitAfterSourceExtract(string $target_path): void
    {
        if (empty($this->after_source_extract_callbacks)) {
            logger()->debug("No after-source-extract hooks registered for [{$this->name}]");
            return;
        }

        logger()->debug('Executing ' . count($this->after_source_extract_callbacks) . " after-source-extract hook(s) for [{$this->name}]");
        foreach ($this->after_source_extract_callbacks as $callback) {
            $callback_name = is_array($callback) ? (is_object($callback[0]) ? get_class($callback[0]) : $callback[0]) . '::' . $callback[1] : (is_string($callback) ? $callback : 'Closure');
            logger()->debug("  🪝 Running hook: {$callback_name}");
            ApplicationContext::invoke($callback, ['target_path' => $target_path, Artifact::class => $this]);
        }
    }

    /**
     * Emit all after binary extract callbacks for the specified platform.
     *
     * @param null|string $target_path The directory where binary was extracted
     * @param string      $platform    The platform string (e.g., 'linux-x86_64')
     */
    public function emitAfterBinaryExtract(?string $target_path, string $platform): void
    {
        if (empty($this->after_binary_extract_callbacks)) {
            logger()->debug("No after-binary-extract hooks registered for [{$this->name}]");
            return;
        }

        $executed = 0;
        foreach ($this->after_binary_extract_callbacks as $item) {
            $callback_platforms = $item['platforms'];

            // Empty platforms array means all platforms
            if (empty($callback_platforms) || in_array($platform, $callback_platforms, true)) {
                $callback = $item['callback'];
                $callback_name = is_array($callback) ? (is_object($callback[0]) ? get_class($callback[0]) : $callback[0]) . '::' . $callback[1] : (is_string($callback) ? $callback : 'Closure');
                logger()->debug("  🪝 Running hook: {$callback_name} (platform: {$platform})");
                ApplicationContext::invoke($callback, [
                    'target_path' => $target_path,
                    'platform' => $platform,
                    Artifact::class => $this,
                ]);
                ++$executed;
            }
        }

        logger()->debug("Executed {$executed} after-binary-extract hook(s) for [{$this->name}] on platform [{$platform}]");
    }

    /**
     * Replaces variables in the extract path.
     *
     * @param string $extract the extract path with variables
     */
    private function replaceExtractPathVariables(string $extract): string
    {
        $replacement = [
            '{artifact_name}' => $this->name,
            '{pkg_root_path}' => PKG_ROOT_PATH,
            '{build_root_path}' => BUILD_ROOT_PATH,
            '{php_sdk_path}' => getenv('PHP_SDK_PATH') ?: WORKING_DIR . '/php-sdk-binary-tools',
            '{working_dir}' => WORKING_DIR,
            '{download_path}' => DOWNLOAD_PATH,
            '{source_path}' => SOURCE_PATH,
        ];
        return str_replace(array_keys($replacement), array_values($replacement), $extract);
    }
}
