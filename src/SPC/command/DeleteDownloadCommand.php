<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\store\Downloader;
use SPC\store\FileSystem;
use SPC\store\LockFile;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('del-download', 'Remove locked download source or package using name', ['delete-download', 'del-down'])]
class DeleteDownloadCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addArgument('sources', InputArgument::REQUIRED, 'The sources/packages will be deleted, comma separated');
        $this->addOption('all', 'A', null, 'Delete all downloaded and locked sources/packages');
        $this->addOption('pre-built-only', 'W', null, 'Delete only pre-built sources/packages, not the original ones');
        $this->addOption('source-only', 'S', null, 'Delete only sources, not the pre-built packages');
    }

    public function initialize(InputInterface $input, OutputInterface $output): void
    {
        if ($input->getOption('all')) {
            $input->setArgument('sources', '');
        }
        parent::initialize($input, $output);
    }

    public function handle(): int
    {
        // get source list that will be downloaded
        $sources = array_map('trim', array_filter(explode(',', $this->getArgument('sources'))));
        if (empty($sources)) {
            logger()->notice('Removing downloads/ directory ...');
            FileSystem::removeDir(DOWNLOAD_PATH);
            logger()->info('Removed downloads/ dir!');
            return static::SUCCESS;
        }
        $chosen_sources = $sources;

        $deleted_sources = [];
        foreach ($chosen_sources as $source) {
            $source = trim($source);
            if (LockFile::get($source) && !$this->getOption('pre-built-only')) {
                $deleted_sources[] = $source;
            }
            if (LockFile::get(Downloader::getPreBuiltLockName($source)) && !$this->getOption('source-only')) {
                $deleted_sources[] = Downloader::getPreBuiltLockName($source);
            }
        }

        foreach ($deleted_sources as $lock_name) {
            $lock = LockFile::get($lock_name);
            // remove download file/dir if exists
            if ($lock['source_type'] === SPC_SOURCE_ARCHIVE) {
                if (file_exists($path = FileSystem::convertPath(DOWNLOAD_PATH . '/' . $lock['filename']))) {
                    logger()->info('Deleting file ' . $path);
                    unlink($path);
                } else {
                    logger()->warning("Source/Package [{$lock_name}] file not found, skip deleting file.");
                }
            } else {
                if (is_dir($path = FileSystem::convertPath(DOWNLOAD_PATH . '/' . $lock['dirname']))) {
                    logger()->info('Deleting dir ' . $path);
                    FileSystem::removeDir($path);
                } else {
                    logger()->warning("Source/Package [{$lock_name}] directory not found, skip deleting dir.");
                }
            }
            // remove locked sources
            LockFile::put($lock_name, null);
        }
        logger()->info('Delete success!');
        return static::SUCCESS;
    }
}
