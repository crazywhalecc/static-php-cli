<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\builder\traits\UnixSystemUtilTrait;
use SPC\store\SourceExtractor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('extract', 'Extract required sources')]
class ExtractCommand extends BaseCommand
{
    use UnixSystemUtilTrait;

    protected string $php_major_ver;

    public function configure()
    {
        $this->addArgument('sources', InputArgument::REQUIRED, 'The sources will be compiled, comma separated');
    }

    public function handle(): int
    {
        $sources = array_map('trim', array_filter(explode(',', $this->getArgument('sources'))));
        if (empty($sources)) {
            $this->output->writeln('<erorr>sources cannot be empty, at least contain one !</erorr>');
            return 1;
        }
        SourceExtractor::initSource(sources: $sources);
        logger()->info('Extract done !');
        return 0;
    }
}
