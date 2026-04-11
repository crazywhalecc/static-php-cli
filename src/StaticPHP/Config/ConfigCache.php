<?php

declare(strict_types=1);

namespace StaticPHP\Config;

/**
 * Simple parse-result cache for YAML/JSON config files.
 *
 * Key   = raw file content string (files are small, direct comparison is fine).
 * Value = parsed PHP array.
 *
 * Storage: <cwd>/.spc.cache.php  (plain PHP, var_export'd array).
 * Written once on shutdown when any new entry was added.
 */
class ConfigCache
{
    private static ?array $cache = null;

    private static bool $dirty = false;

    /**
     * Return the cached parsed result for $content, or null on miss.
     */
    public static function get(string $content): ?array
    {
        self::load();
        return self::$cache[$content] ?? null;
    }

    /**
     * Store a parsed result. Will be persisted to disk on shutdown.
     */
    public static function set(string $content, array $data): void
    {
        self::load();
        self::$cache[$content] = $data;
        self::$dirty = true;
    }

    /**
     * Write cache to disk if anything changed. Called automatically on shutdown.
     */
    public static function flush(): void
    {
        if (!self::$dirty) {
            return;
        }
        file_put_contents(self::cachePath(), '<?php return ' . var_export(self::$cache, true) . ";\n");
        self::$dirty = false;
    }

    private static function cachePath(): string
    {
        return getcwd() . '/.spc.cache.php';
    }

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        $path = self::cachePath();
        self::$cache = file_exists($path) ? (require $path) : [];
        register_shutdown_function([self::class, 'flush']);
    }
}
