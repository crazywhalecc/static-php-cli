<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use StaticPHP\Package\PackageInstaller;
use StaticPHP\Util\V2CompatLayer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('build:libs', 'Build specified library packages')]
class BuildLibsCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addArgument('libraries', InputArgument::REQUIRED, 'The library packages will be compiled, comma separated');
        // Builder options
        $this->getDefinition()->addOptions([
            new InputOption('with-suggests', ['L', 'E'], null, 'Resolve and install suggested packages as well'),
            new InputOption('with-packages', null, InputOption::VALUE_REQUIRED, 'add additional packages to install/build, comma separated', ''),
            new InputOption('no-download', null, null, 'Skip downloading artifacts (use existing cached files)'),
            ...V2CompatLayer::getLegacyBuildOptions(),
        ]);
    }

    public function handle(): int
    {
        $libs = parse_comma_list($this->input->getArgument('libraries'));

        $installer = new PackageInstaller($this->input->getOptions());
        foreach ($libs as $lib) {
            $installer->addBuildPackage($lib);
        }
        $installer->run();
        return static::SUCCESS;
    }
}
