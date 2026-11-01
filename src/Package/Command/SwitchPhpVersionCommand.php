<?php

declare(strict_types=1);

namespace Package\Command;

use Package\Target\php;
use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\DownloaderOptions;
use StaticPHP\Command\BaseCommand;
use StaticPHP\DI\ApplicationContext;
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

        // Remove extracted source directory if exists and --keep-source not set
        $source_dir = SOURCE_PATH . '/php-src';
        if (!$this->getOption('keep-source') && is_dir($source_dir)) {
            $this->output->writeln('<info>Removing extracted PHP source directory...</info>');
            InteractiveTerm::indicateProgress('Removing: ' . $source_dir);
            FileSystem::removeDir($source_dir);
            InteractiveTerm::finish('Removed: ' . $source_dir);
        }

        // Extension sources are extracted to source/ext-* and linked into php-src/ext, so their
        // build state (objects, dependency files, patched config.m4, phpize leftovers) lives
        // outside source/php-src and is only valid for the PHP version that produced it.
        $this->cleanExtensionSources();

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
     * Delete every extracted extension source tree under source/ext-*, returning the count.
     */
    public static function removeExtensionSources(string $source_path): int
    {
        $removed = 0;
        foreach (glob(FileSystem::convertPath("{$source_path}/ext-*"), GLOB_ONLYDIR) ?: [] as $ext_dir) {
            if (FileSystem::isLink($ext_dir)) {
                FileSystem::removeLink($ext_dir);
            } else {
                FileSystem::removeDir($ext_dir);
            }
            ++$removed;
        }

        return $removed;
    }

    /**
     * Remove the extracted extension source trees (source/ext-*) of the previous PHP version.
     *
     * Extension sources are linked into php-src/ext for in-tree builds, so their objects,
     * dependency files, patched config.m4 and phpize leftovers live outside source/php-src and
     * would otherwise leak into the next PHP version's build. The original archives stay in
     * downloads/ and are re-extracted automatically on the next build.
     */
    private function cleanExtensionSources(): void
    {
        $removed = self::removeExtensionSources(SOURCE_PATH);

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
