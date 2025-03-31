<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\builder\traits\UnixSystemUtilTrait;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\SourceManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('extract', 'Extract required sources', ['extract-source'])]
class ExtractCommand extends BaseCommand
{
    use UnixSystemUtilTrait;

    public function configure(): void
    {
        $this->addArgument('sources', InputArgument::REQUIRED, 'The sources will be compiled, comma separated');
        $this->addOption('source-only', null, null, 'Only check the source exist, do not check the lib and ext');
    }

    /**
     * @throws WrongUsageException
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function handle(): int
    {
        $sources = array_map('trim', array_filter(explode(',', $this->getArgument('sources'))));
        if (empty($sources)) {
            $this->output->writeln('<error>sources cannot be empty, at least contain one !</error>');
            return static::FAILURE;
        }
        SourceManager::initSource(sources: $sources, source_only: $this->getOption('source-only'));
        logger()->info('Extract done !');
        return static::SUCCESS;
    }
}
