<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\command\BaseCommand;
use SPC\store\Config;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('dev:ext-all', 'Dev command', ['list-ext'])]
class AllExtCommand extends BaseCommand
{
    public function configure()
    {
        $this->addOption('line', 'l', null, 'Show with separate lines');
    }

    public function handle(): int
    {
        $this->output->writeln(implode($this->input->getOption('line') ? PHP_EOL : ',', array_keys(Config::getExts())));
        return static::SUCCESS;
    }
}
