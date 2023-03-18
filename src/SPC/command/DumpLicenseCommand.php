<?php

declare(strict_types=1);

namespace SPC\command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 修改 config 后对其 kv 进行排序的操作
 */
class DumpLicenseCommand extends BaseCommand
{
    protected static $defaultName = 'dump-license';

    public function configure()
    {
        $this->setDescription('Dump licenses for required libraries');
        $this->addArgument('config-name', InputArgument::REQUIRED, 'Your config to be sorted, you can sort "lib", "source" and "ext".');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>not implemented</info>');
        return 1;
    }
}
