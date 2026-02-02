<?php

declare(strict_types=1);

namespace StaticPHP\Util;

use StaticPHP\Artifact\Artifact;
use StaticPHP\Config\ArtifactConfig;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Registry\ArtifactLoader;

/**
 * License dumper for v3, dumps artifact license files to target directory
 */
class LicenseDumper
{
    /** @var array<string> Artifact names to dump */
    private array $artifacts = [];

    /**
     * Add artifacts by name.
     *
     * @param array<string> $artifacts Artifact names
     */
    public function addArtifacts(array $artifacts): self
    {
        $this->artifacts = array_unique(array_merge($this->artifacts, $artifacts));
        return $this;
    }

    /**
     * Dump all collected artifact licenses to target directory.
     *
     * @param  string $target_dir Target directory path
     * @return bool   True on success
     */
    public function dump(string $target_dir): bool
    {
        // Create target directory if not exists (don't clean existing files)
        if (!is_dir($target_dir)) {
            FileSystem::createDir($target_dir);
        } else {
            logger()->debug("Target directory exists, will append/update licenses: {$target_dir}");
        }

        $license_summary = [];
        $dumped_count = 0;

        foreach ($this->artifacts as $artifact_name) {
            $artifact = ArtifactLoader::getArtifactInstance($artifact_name);
            if ($artifact === null) {
                logger()->warning("Artifact not found, skipping: {$artifact_name}");
                continue;
            }

            try {
                $result = $this->dumpArtifactLicense($artifact, $target_dir, $license_summary);
                if ($result) {
                    ++$dumped_count;
                }
            } catch (\Throwable $e) {
                logger()->warning("Failed to dump license for {$artifact_name}: {$e->getMessage()}");
            }
        }

        // Generate LICENSE-SUMMARY.json (read-modify-write)
        $this->generateSummary($target_dir, $license_summary);

        logger()->info("Successfully dumped {$dumped_count} license(s) to: {$target_dir}");
        return true;
    }

    /**
     * Dump license for a single artifact.
     *
     * @param  Artifact             $artifact         Artifact instance
     * @param  string               $target_dir       Target directory
     * @param  array<string, array> &$license_summary Summary data to populate
     * @return bool                 True if dumped
     * @throws SPCInternalException
     */
    private function dumpArtifactLicense(Artifact $artifact, string $target_dir, array &$license_summary): bool
    {
        $artifact_name = $artifact->getName();

        // Get metadata from ArtifactConfig
        $artifact_config = ArtifactConfig::get($artifact_name);
        $config = $artifact_config['metadata'] ?? null;

        if ($config === null) {
            logger()->debug("No metadata for artifact: {$artifact_name}");
            return false;
        }

        $license_type = $config['license'] ?? null;
        $license_files = $config['license-files'] ?? [];

        // Ensure license_files is array
        if (is_string($license_files)) {
            $license_files = [$license_files];
        }

        if (empty($license_files)) {
            logger()->debug("No license files specified for: {$artifact_name}");
            return false;
        }

        // Record in summary
        $summary_license = $license_type ?? 'Custom';
        if (!isset($license_summary[$summary_license])) {
            $license_summary[$summary_license] = [];
        }
        $license_summary[$summary_license][] = $artifact_name;

        // Dump each license file
        $file_count = count($license_files);
        $dumped_any = false;

        foreach ($license_files as $index => $license_file_path) {
            // Construct output filename
            if ($file_count === 1) {
                $output_filename = "{$artifact_name}_LICENSE.txt";
            } else {
                $output_filename = "{$artifact_name}_LICENSE_{$index}.txt";
            }

            $output_path = "{$target_dir}/{$output_filename}";

            // Skip if file already exists (avoid duplicate writes)
            if (file_exists($output_path)) {
                logger()->debug("License file already exists, skipping: {$output_filename}");
                $dumped_any = true; // Still count as dumped
                continue;
            }

            // Try to read license file from source directory
            $license_content = $this->readLicenseFile($artifact, $license_file_path);
            if ($license_content === null) {
                logger()->warning("License file not found for {$artifact_name}: {$license_file_path}");
                continue;
            }

            // Write to target
            if (file_put_contents($output_path, $license_content) === false) {
                throw new SPCInternalException("Failed to write license file: {$output_path}");
            }

            logger()->info("Dumped license: {$output_filename}");
            $dumped_any = true;
        }

        return $dumped_any;
    }

    /**
     * Read license file content from artifact's source directory.
     *
     * @param  Artifact    $artifact          Artifact instance
     * @param  string      $license_file_path Relative path to license file
     * @return null|string License content, or null if not found
     */
    private function readLicenseFile(Artifact $artifact, string $license_file_path): ?string
    {
        $artifact_name = $artifact->getName();

        // replace
        if (str_starts_with($license_file_path, '@/')) {
            $license_file_path = str_replace('@/', ROOT_DIR . '/src/globals/licenses/', $license_file_path);
        }

        $source_dir = $artifact->getSourceDir();
        if (FileSystem::isRelativePath($license_file_path)) {
            $full_path = "{$source_dir}/{$license_file_path}";
        } else {
            $full_path = $license_file_path;
        }
        // Try source directory first (if extracted)
        if ($artifact->isSourceExtracted() || file_exists($full_path)) {
            logger()->debug("Checking license file: {$full_path}");
            if (file_exists($full_path)) {
                logger()->info("Reading license from source: {$full_path}");
                return file_get_contents($full_path);
            }
        } else {
            logger()->warning("Artifact source not extracted: {$artifact_name}");
        }

        // Fallback: try SOURCE_PATH directly
        $fallback_path = SOURCE_PATH . "/{$artifact_name}/{$license_file_path}";
        logger()->debug("Checking fallback path: {$fallback_path}");
        if (file_exists($fallback_path)) {
            logger()->info("Reading license from fallback path: {$fallback_path}");
            return file_get_contents($fallback_path);
        }

        logger()->debug("License file not found in any location for {$artifact_name}");
        return null;
    }

    /**
     * Generate LICENSE-SUMMARY.json file with read-modify-write support.
     *
     * @param string               $target_dir      Target directory
     * @param array<string, array> $license_summary License summary data (license_type => [artifacts])
     */
    private function generateSummary(string $target_dir, array $license_summary): void
    {
        if (empty($license_summary)) {
            logger()->debug('No licenses to summarize');
            return;
        }

        $summary_file = "{$target_dir}/LICENSE-SUMMARY.json";

        // Read existing summary if exists
        $existing_data = [];
        if (file_exists($summary_file)) {
            $content = file_get_contents($summary_file);
            $existing_data = json_decode($content, true) ?? [];
            logger()->debug('Loaded existing LICENSE-SUMMARY.json');
        }

        // Initialize structure
        if (!isset($existing_data['artifacts'])) {
            $existing_data['artifacts'] = [];
        }
        if (!isset($existing_data['summary'])) {
            $existing_data['summary'] = ['license_types' => []];
        }

        // Merge new license information
        foreach ($license_summary as $license_type => $artifacts) {
            foreach ($artifacts as $artifact_name) {
                // Add/update artifact info
                $existing_data['artifacts'][$artifact_name] = [
                    'license' => $license_type,
                    'dumped_at' => date('Y-m-d H:i:s'),
                ];

                // Update license_types summary
                if (!isset($existing_data['summary']['license_types'][$license_type])) {
                    $existing_data['summary']['license_types'][$license_type] = [];
                }
                if (!in_array($artifact_name, $existing_data['summary']['license_types'][$license_type])) {
                    $existing_data['summary']['license_types'][$license_type][] = $artifact_name;
                }
            }
        }

        // Sort license types and artifacts
        ksort($existing_data['summary']['license_types']);
        foreach ($existing_data['summary']['license_types'] as &$artifacts) {
            sort($artifacts);
        }
        ksort($existing_data['artifacts']);

        // Update totals
        $existing_data['summary']['total_artifacts'] = count($existing_data['artifacts']);
        $existing_data['summary']['total_license_types'] = count($existing_data['summary']['license_types']);
        $existing_data['summary']['last_updated'] = date('Y-m-d H:i:s');

        // Write JSON file
        file_put_contents(
            $summary_file,
            json_encode($existing_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        logger()->info('Generated LICENSE-SUMMARY.json with ' . $existing_data['summary']['total_artifacts'] . ' artifact(s)');
    }
}
