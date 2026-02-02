<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('build:libs', 'Build specified library packages')]
class BuildLibsCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addArgument('libraries', InputArgument::REQUIRED, 'The library packages will be compiled, comma separated');
    }

    public function handle(): int
    {
        $libs = parse_comma_list($this->input->getArgument('libraries'));

        $installer = new \StaticPHP\Package\PackageInstaller($this->input->getOptions());
        foreach ($libs as $lib) {
            $installer->addBuildPackage($lib);
        }
        $installer->run();
        return static::SUCCESS;
    }
}
