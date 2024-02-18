<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\exception\DownloaderException;
use SPC\exception\FileSystemException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
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
    }

    public function initialize(InputInterface $input, OutputInterface $output): void
    {
        if ($input->getOption('all')) {
            $input->setArgument('sources', '');
        }
        parent::initialize($input, $output);
    }

    /**
     * @throws FileSystemException
     */
    public function handle(): int
    {
        try {
            // get source list that will be downloaded
            $sources = array_map('trim', array_filter(explode(',', $this->getArgument('sources'))));
            if (empty($sources)) {
                logger()->notice('Removing downloads/ directory ...');
                FileSystem::removeDir(DOWNLOAD_PATH);
                logger()->info('Removed downloads/ dir!');
                return static::SUCCESS;
            }
            $chosen_sources = $sources;
            $lock = json_decode(FileSystem::readFile(DOWNLOAD_PATH . '/.lock.json'), true) ?? [];

            foreach ($chosen_sources as $source) {
                $source = trim($source);
                if (!isset($lock[$source])) {
                    logger()->warning("Source/Package [{$source}] not locked or not downloaded, skipped.");
                    continue;
                }
                // remove download file/dir if exists
                if ($lock[$source]['source_type'] === 'archive') {
                    if (file_exists($path = FileSystem::convertPath(DOWNLOAD_PATH . '/' . $lock[$source]['filename']))) {
                        logger()->info('Deleting file ' . $path);
                        unlink($path);
                    } else {
                        logger()->warning("Source/Package [{$source}] file not found, skip deleting file.");
                    }
                } else {
                    if (is_dir($path = FileSystem::convertPath(DOWNLOAD_PATH . '/' . $lock[$source]['dirname']))) {
                        logger()->info('Deleting dir ' . $path);
                        FileSystem::removeDir($path);
                    } else {
                        logger()->warning("Source/Package [{$source}] directory not found, skip deleting dir.");
                    }
                }
                // remove locked sources
                unset($lock[$source]);
            }
            FileSystem::writeFile(DOWNLOAD_PATH . '/.lock.json', json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            logger()->info('Delete success!');
            return static::SUCCESS;
        } catch (DownloaderException $e) {
            logger()->error($e->getMessage());
            return static::FAILURE;
        } catch (WrongUsageException $e) {
            logger()->critical($e->getMessage());
            return static::FAILURE;
        }
    }
}
