<?php

declare(strict_types=1);

namespace StaticPHP\Command\Dev;

use StaticPHP\Command\BaseCommand;
use StaticPHP\Package\PackageInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('dev:is-installed', 'Check if a package is installed correctly', ['is-installed'], true)]
class IsInstalledCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->no_motd = true;
        $this->addArgument('package', InputArgument::REQUIRED, 'The package name to check installation status');
    }

    public function handle(): int
    {
        $installer = new PackageInstaller();
        $package = $this->input->getArgument('package');
        $installer->addInstallPackage($package);
        $installed = $installer->isPackageInstalled($package);
        if ($installed) {
            $this->output->writeln("<info>Package [{$package}] is installed correctly.</info>");
            return static::SUCCESS;
        }
        $this->output->writeln("<error>Package [{$package}] is not installed.</error>");
        return static::FAILURE;
    }
}
