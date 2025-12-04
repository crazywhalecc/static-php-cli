<?php

declare(strict_types=1);

namespace Package\Command;

use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\DownloaderOptions;
use StaticPHP\Command\BaseCommand;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Package\PackageLoader;
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
            'PHP version (e.g., 8.4, 8.3, 8.2, 8.1, 8.0, 7.4, or specific like 8.4.5)',
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
            $this->output->writeln('<comment>Supported formats: 7.4, 8.0, 8.1, 8.2, 8.3, 8.4, or specific version like 8.4.5</comment>');
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
     * Validate PHP version format.
     *
     * Accepts:
     * - Major.Minor format: 7.4, 8.0, 8.1, 8.2, 8.3, 8.4
     * - Full version format: 8.4.5, 8.3.12, etc.
     */
    private function isValidPhpVersion(string $version): bool
    {
        // Check major.minor format (e.g., 8.4)
        if (in_array($version, ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'], true)) {
            return true;
        }

        // Check full version format (e.g., 8.4.5)
        if (preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return true;
        }

        return false;
    }
}
