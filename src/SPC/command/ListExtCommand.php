<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\builder\traits\NoMotdTrait;
use SPC\store\Config;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListExtCommand extends BaseCommand
{
    use NoMotdTrait;

    protected static $defaultName = 'list-ext';

    public function configure()
    {
        $this->setDescription('List supported extensions');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        foreach (Config::getExts() as $ext => $meta) {
            echo $ext . PHP_EOL;
        }
        return 0;
    }
}
