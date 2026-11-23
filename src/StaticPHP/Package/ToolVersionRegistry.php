<?php

declare(strict_types=1);

namespace StaticPHP\Package;

/**
 * Tracks the version actually installed on disk for tool packages (PKG_ROOT_PATH), separate from
 * the download cache (ArtifactCache). Tools are installed once and reused across many builds, so
 * the download cache (which only reflects the last download) can drift from what's really on disk
 * (e.g. cache cleared, or installed a long time ago via `doctor`). This registry is the source of
 * truth for "what version is currently installed", used by ToolPackage::getInstalledVersion() and
 * the `check-update --installed` flag.
 *
 * Backed by a small JSON file at PKG_ROOT_PATH/.spc-tool-versions.json, mirroring the same
 * read-once/write-through pattern used by StaticPHP\Artifact\ArtifactCache.
 */
class ToolVersionRegistry
{
    /** @var null|array<string, array{version: null|string, platform: string, installed_at: string}> */
    private static ?array $data = null;

    /**
     * Get the recorded installed version for a tool, or null if never recorded (not a tool
     * package, not installed yet, or the artifact doesn't expose a version).
     */
    public static function get(string $tool_name): ?string
    {
        self::load();
        return self::$data[$tool_name]['version'] ?? null;
    }

    /**
     * Record the version currently installed for a tool, or null if never recorded (not a tool
     * package, not installed yet, or the artifact doesn't expose a version). The platform is the
     * artifact's binary platform (host platform for tools), since tools execute on the build
     * machine even during cross builds. Recorded regardless of whether extraction actually ran
     * (keeps the registry self-healing if it was deleted separately from the installed files).
     *
     * @param string      $tool_name Name of the tool package
     * @param null|string $version   Version currently installed, or null
     * @param string      $platform  Platform the tool binary is installed for (see Artifact::getBinaryPlatform())
     */
    public static function record(string $tool_name, ?string $version, string $platform): void
    {
        self::load();
        self::$data[$tool_name] = [
            'version' => $version,
            'platform' => $platform,
            'installed_at' => date('c'),
        ];
        self::save();
    }

    private static function getPath(): string
    {
        return PKG_ROOT_PATH . '/.spc-tool-versions.json';
    }

    private static function load(): void
    {
        if (self::$data !== null) {
            return;
        }
        $path = self::getPath();
        if (!file_exists($path)) {
            self::$data = [];
            return;
        }
        $content = file_get_contents($path);
        self::$data = is_string($content) ? (json_decode($content, true) ?: []) : [];
    }

    private static function save(): void
    {
        $path = self::getPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode(self::$data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
