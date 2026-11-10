<?php

declare(strict_types=1);

namespace Package\Command;

use Package\Target\php;
use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\DownloaderOptions;
use StaticPHP\Command\BaseCommand;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\ExecutionException;
use StaticPHP\Registry\PackageLoader;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('switch-php-version', description: 'Switch downloaded PHP version')]
class SwitchPhpVersionCommand extends BaseCommand
{
    protected bool $no_motd = true;

    public function configure(): void
    {
        $this->addArgument(
            'php-version',
            InputArgument::REQUIRED,
            'PHP version (e.g., ' . implode(', ', php::SUPPORTED_MAJOR_VERSIONS) . ' or specific like 8.4.5)',
        );

        // Downloader options
        $this->getDefinition()->addOptions(DownloaderOptions::getConsoleOptions());

        // Additional options
        $this->addOption('keep-source', null, null, 'Keep extracted source directory (do not remove source/php-src)');
    }

    public function handle(): int
    {
        $php_ver = $this->getArgument('php-version');

        // Validate version format
        if (!$this->isValidPhpVersion($php_ver)) {
            $this->output->writeln("<error>Invalid PHP version '{$php_ver}'!</error>");
            $this->output->writeln('<comment>Supported formats: ' . implode(', ', php::SUPPORTED_MAJOR_VERSIONS) . ', or specific version like 8.4.5</comment>');
            return static::FAILURE;
        }

        $cache = ApplicationContext::get(ArtifactCache::class);

        // Check if php-src is already locked
        $source_info = $cache->getSourceInfo('php-src');
        if ($source_info !== null) {
            $current_version = $source_info['version'] ?? 'unknown';
            $this->output->writeln("<info>Current PHP version: {$current_version}, removing old PHP source cache...");

            // Remove cache entry and optionally the downloaded file
            $cache->removeSource('php-src', delete_file: true);
        }

        // Collect the extension source trees linked into source/php-src/ext before php-src is
        // removed: their build state is only valid for the PHP version that produced it.
        $linked_extension_sources = self::collectLinkedExtensionSources(SOURCE_PATH);

        // Remove extracted source directory if exists and --keep-source not set
        $source_dir = SOURCE_PATH . '/php-src';
        if (!$this->getOption('keep-source') && is_dir($source_dir)) {
            $this->output->writeln('<info>Removing extracted PHP source directory...</info>');
            InteractiveTerm::indicateProgress('Removing: ' . $source_dir);
            FileSystem::removeDir($source_dir);
            if (file_exists($source_dir)) {
                InteractiveTerm::finish('Failed: ' . $source_dir, false);
                throw new ExecutionException(
                    "Failed to remove the extracted PHP source directory: {$source_dir}\n" .
                    'The PHP version switch was aborted: continuing would build the new PHP on top of ' .
                    'stale sources and object files from the previous version (wrong headers/ABI, ' .
                    'undefined symbols at link time).' . "\n" .
                    'Remove the directory manually (on Windows: rmdir /s /q "' . FileSystem::convertPath($source_dir) . '") and retry.',
                );
            }
            InteractiveTerm::finish('Removed: ' . $source_dir);
        }

        // Extension sources are extracted to source/ and linked into php-src/ext, so their build
        // state (objects, dependency files, patched config.m4, phpize leftovers) lives outside
        // source/php-src and is only valid for the PHP version that produced it.
        $this->cleanExtensionSources($linked_extension_sources);

        // Download new PHP source
        $this->output->writeln("<info>Downloading PHP {$php_ver} source...</info>");

        $this->input->setOption('with-php', $php_ver);

        $downloaderOptions = DownloaderOptions::extractFromConsoleOptions($this->input->getOptions());
        $downloader = new ArtifactDownloader($downloaderOptions);

        // Get php-src artifact from php package
        $php_package = PackageLoader::getPackage('php');
        $artifact = $php_package->getArtifact();

        if ($artifact === null) {
            $this->output->writeln('<error>Failed to get php-src artifact!</error>');
            return static::FAILURE;
        }

        $downloader->add($artifact);
        $downloader->download();

        // Get the new version info
        $new_source_info = $cache->getSourceInfo('php-src');
        $new_version = $new_source_info['version'] ?? $php_ver;

        $this->output->writeln('');
        $this->output->writeln("<info>Successfully switched to PHP {$new_version}!</info>");

        return static::SUCCESS;
    }

    /**
     * Delete the extracted extension source trees of the previous PHP version, returning the count.
     *
     * Targets are the source/ext-* directories plus any extra source roots passed in (usually the
     * ones linked into php-src/ext, see collectLinkedExtensionSources()).
     *
     * @param  string[]           $extra_source_dirs Additional source roots to remove
     * @return int                Number of removed source trees
     * @throws ExecutionException When an extracted tree cannot be removed completely
     */
    public static function removeExtensionSources(string $source_path, array $extra_source_dirs = []): int
    {
        $targets = [];
        foreach (glob(FileSystem::convertPath("{$source_path}/ext-*"), GLOB_ONLYDIR) ?: [] as $ext_dir) {
            $targets[$ext_dir] = true;
        }
        foreach ($extra_source_dirs as $extra_dir) {
            if (is_dir($extra_dir) || FileSystem::isLink($extra_dir)) {
                $targets[FileSystem::convertPath($extra_dir)] = true;
            }
        }

        $removed = 0;
        foreach (array_keys($targets) as $ext_dir) {
            if (FileSystem::isLink($ext_dir)) {
                $ok = FileSystem::removeLink($ext_dir);
            } else {
                $ok = FileSystem::removeDir($ext_dir);
            }
            if (!$ok || file_exists($ext_dir) || FileSystem::isLink($ext_dir)) {
                throw new ExecutionException(
                    "Failed to remove extracted extension source directory: {$ext_dir}\n" .
                    'Its build state (objects, generated headers, patched sources) is only valid for the ' .
                    'PHP version that produced it and would leak into the next build.' . "\n" .
                    'Remove it manually (on Windows: rmdir /s /q "' . FileSystem::convertPath($ext_dir) . '") and retry.',
                );
            }
            ++$removed;
        }

        return $removed;
    }

    /**
     * Collect the extracted source trees linked into source/php-src/ext.
     *
     * Extension artifacts are extracted under source/ with their artifact name, which is not
     * necessarily "ext-<extension>" (e.g. the glfw extension uses source/glfw), so cleaning
     * source/ext-* alone misses them. Their build state (objects, .dep files, patched configure
     * scripts) is only valid for the PHP version that produced it and must be dropped together
     * with php-src, otherwise stale dependency files leak into the next version's build.
     *
     * Must be called before source/php-src is removed.
     *
     * @return string[] Absolute source root paths managed by spc inside source/
     */
    public static function collectLinkedExtensionSources(string $source_path): array
    {
        $source_real = realpath($source_path);
        $source_root = rtrim(FileSystem::convertPath($source_real !== false ? $source_real : $source_path), '/\\') . DIRECTORY_SEPARATOR;
        $php_src_dir = $source_root . 'php-src' . DIRECTORY_SEPARATOR;
        $found = [];

        foreach (glob($php_src_dir . 'ext' . DIRECTORY_SEPARATOR . '*') ?: [] as $link_dir) {
            if (!FileSystem::isLink($link_dir)) {
                continue;
            }
            $target = realpath($link_dir);
            if ($target === false) {
                continue;
            }
            $target = FileSystem::convertPath($target);
            // Only remove sources spc manages under source/: never a user-provided local tree,
            // and never something inside php-src itself (removed separately).
            if (!str_starts_with($target, $source_root) || str_starts_with($target, $php_src_dir)) {
                continue;
            }
            $found[$target] = true;
        }

        return array_keys($found);
    }

    /**
     * Remove the extracted extension source trees of the previous PHP version.
     *
     * Extension sources are linked into php-src/ext for in-tree builds, so their objects,
     * dependency files, patched config.m4 and phpize leftovers live outside source/php-src and
     * would otherwise leak into the next PHP version's build. The original archives stay in
     * downloads/ and are re-extracted automatically on the next build.
     *
     * @param string[] $linked_source_dirs Source roots collected before php-src removal
     */
    private function cleanExtensionSources(array $linked_source_dirs = []): void
    {
        $removed = self::removeExtensionSources(SOURCE_PATH, $linked_source_dirs);

        if ($removed > 0) {
            $this->output->writeln("<info>Removed {$removed} extracted extension source director" . ($removed === 1 ? 'y' : 'ies') . '; they will be re-extracted from the cached archives on the next build.</info>');
        }
    }

    /**
     * Validate PHP version format.
     *
     * Accepts:
     * - Major.Minor format, e.g. 7.4
     * - Full version format, e.g. 8.4.5, 8.3.12, etc.
     * - Pre-release format, e.g. 8.6.0RC1
     */
    private function isValidPhpVersion(string $version): bool
    {
        // Check major.minor format (e.g., 8.4)
        if (in_array($version, php::SUPPORTED_MAJOR_VERSIONS, true)) {
            return true;
        }

        // Check full version format (e.g., 8.4.5, 8.6.0RC1)
        if (preg_match('/^\d+\.\d+\.\d+(?:(?:alpha|beta|RC)\d+)?$/', $version)) {
            return true;
        }

        return false;
    }
}
