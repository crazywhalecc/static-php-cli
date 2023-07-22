<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\command\BaseCommand;
use SPC\store\Config;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('dev:ext-all', 'Dev command')]
class AllExtCommand extends BaseCommand
{
    public function configure()
    {
    }

    public function handle(): int
    {
        $this->output->writeln(implode(',', array_keys(Config::getExts())));

        return 0;
    }
}
