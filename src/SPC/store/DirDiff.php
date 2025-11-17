<?php

declare(strict_types=1);

namespace SPC\store;

/**
 * A util class to diff directory file increments.
 */
class DirDiff
{
    protected array $before = [];

    protected array $before_file_hashes = [];

    public function __construct(protected string $dir, protected bool $track_content_changes = false)
    {
        $this->reset();
    }

    /**
     * Reset the baseline to current state.
     */
    public function reset(): void
    {
        $this->before = FileSystem::scanDirFiles($this->dir, relative: true) ?: [];

        if ($this->track_content_changes) {
            $this->before_file_hashes = [];
            foreach ($this->before as $file) {
                $this->before_file_hashes[$file] = md5_file($this->dir . DIRECTORY_SEPARATOR . $file);
            }
        }
    }

    /**
     * Get the list of incremented files.
     *
     * @param  bool          $relative Return relative paths or absolute paths
     * @return array<string> List of incremented files
     */
    public function getIncrementFiles(bool $relative = false): array
    {
        $after = FileSystem::scanDirFiles($this->dir, relative: true) ?: [];
        $diff = array_diff($after, $this->before);
        if ($relative) {
            return $diff;
        }
        return array_map(fn ($f) => $this->dir . DIRECTORY_SEPARATOR . $f, $diff);
    }

    /**
     * Get the list of changed files (including new files).
     *
     * @param  bool          $relative          Return relative paths or absolute paths
     * @param  bool          $include_new_files Include new files as changed files
     * @return array<string> List of changed files
     */
    public function getChangedFiles(bool $relative = false, bool $include_new_files = true): array
    {
        $after = FileSystem::scanDirFiles($this->dir, relative: true) ?: [];
        $changed = [];
        foreach ($after as $file) {
            if (isset($this->before_file_hashes[$file])) {
                $after_hash = md5_file($this->dir . DIRECTORY_SEPARATOR . $file);
                if ($after_hash !== $this->before_file_hashes[$file]) {
                    $changed[] = $file;
                }
            } elseif ($include_new_files) {
                // New file, consider as changed
                $changed[] = $file;
            }
        }
        if ($relative) {
            return $changed;
        }
        return array_map(fn ($f) => $this->dir . DIRECTORY_SEPARATOR . $f, $changed);
    }

    /**
     * Get the list of removed files.
     *
     * @param  bool          $relative Return relative paths or absolute paths
     * @return array<string> List of removed files
     */
    public function getRemovedFiles(bool $relative = false): array
    {
        $after = FileSystem::scanDirFiles($this->dir, relative: true) ?: [];
        $removed = array_diff($this->before, $after);
        if ($relative) {
            return $removed;
        }
        return array_map(fn ($f) => $this->dir . DIRECTORY_SEPARATOR . $f, $removed);
    }
}
