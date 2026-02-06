<?php

declare(strict_types=1);

namespace StaticPHP\Util;

use StaticPHP\Package\Package;

/**
 * BuildRootTracker tracks which files in buildroot directory are created/modified by which package.
 * This helps to understand the file provenance and manage build artifacts.
 */
class BuildRootTracker
{
    /** @var array<string, array{package: string, type: string, files: array<string>}> Tracking data */
    protected array $tracking_data = [];

    protected static string $tracker_file = BUILD_ROOT_PATH . '/.spc-tracker.json';

    protected ?DirDiff $current_diff = null;

    protected ?string $current_package = null;

    protected ?string $current_type = null;

    public function __construct()
    {
        $this->loadTrackingData();
    }

    /**
     * Start tracking for a package.
     *
     * @param Package $package The package to track
     * @param string  $type    The operation type: 'build' or 'install'
     */
    public function startTracking(Package $package, string $type = 'build'): void
    {
        $this->current_package = $package->getName();
        $this->current_type = $type;
        $this->current_diff = new DirDiff(BUILD_ROOT_PATH, false);
    }

    /**
     * Stop tracking and record the changes.
     */
    public function stopTracking(): void
    {
        if ($this->current_diff === null || $this->current_package === null) {
            return;
        }

        $increment_files = $this->current_diff->getIncrementFiles(true);

        if ($increment_files !== []) {
            // Remove buildroot prefix if exists and normalize paths
            $normalized_files = array_map(function ($file) {
                // Remove leading slashes
                return ltrim($file, '/\\');
            }, $increment_files);

            $this->tracking_data[$this->current_package] = [
                'package' => $this->current_package,
                'type' => $this->current_type,
                'files' => array_values($normalized_files),
                'time' => date('Y-m-d H:i:s'),
            ];

            $this->saveTrackingData();
        }

        $this->current_diff = null;
        $this->current_package = null;
        $this->current_type = null;
    }

    /**
     * Get tracking data for a specific package.
     *
     * @param  string     $package_name Package name
     * @return null|array Tracking data or null if not found
     */
    public function getPackageTracking(string $package_name): ?array
    {
        return $this->tracking_data[$package_name] ?? null;
    }

    /**
     * Get all tracking data.
     *
     * @return array<string, array> All tracking data
     */
    public function getAllTracking(): array
    {
        return $this->tracking_data;
    }

    /**
     * Find which package introduced a specific file.
     *
     * @param  string      $file File path (relative to buildroot)
     * @return null|string Package name or null if not found
     */
    public function findFileSource(string $file): ?string
    {
        $file = ltrim($file, '/\\');
        foreach ($this->tracking_data as $package_name => $data) {
            if (in_array($file, $data['files'], true)) {
                return $package_name;
            }
        }
        return null;
    }

    /**
     * Clear tracking data for a specific package.
     *
     * @param string $package_name Package name
     */
    public function clearPackageTracking(string $package_name): void
    {
        unset($this->tracking_data[$package_name]);
        $this->saveTrackingData();
    }

    /**
     * Clear all tracking data.
     */
    public function clearAllTracking(): void
    {
        $this->tracking_data = [];
        $this->saveTrackingData();
    }

    /**
     * Get tracking statistics.
     *
     * @return array{total_packages: int, total_files: int, by_type: array<string, int>}
     */
    public function getStatistics(): array
    {
        $total_files = 0;
        $by_type = [];

        foreach ($this->tracking_data as $data) {
            $total_files += count($data['files']);
            $type = $data['type'];
            $by_type[$type] = ($by_type[$type] ?? 0) + 1;
        }

        return [
            'total_packages' => count($this->tracking_data),
            'total_files' => $total_files,
            'by_type' => $by_type,
        ];
    }

    /**
     * Get the tracker file path.
     */
    public static function getTrackerFilePath(): string
    {
        return self::$tracker_file;
    }

    /**
     * Load tracking data from file.
     */
    protected function loadTrackingData(): void
    {
        if (is_file(self::$tracker_file)) {
            $content = file_get_contents(self::$tracker_file);
            $data = json_decode($content, true);
            if (is_array($data)) {
                $this->tracking_data = $data;
            }
        }
    }

    /**
     * Save tracking data to file.
     */
    protected function saveTrackingData(): void
    {
        FileSystem::createDir(dirname(self::$tracker_file));
        $content = json_encode($this->tracking_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        FileSystem::writeFile(self::$tracker_file, $content);
    }
}
