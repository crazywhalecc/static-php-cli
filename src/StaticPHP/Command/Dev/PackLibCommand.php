<?php

declare(strict_types=1);

namespace StaticPHP\Command\Dev;

use StaticPHP\Command\BaseCommand;
use StaticPHP\Package\PackageInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('dev:pack-lib', 'Packs a library package for distribution')]
class PackLibCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addArgument('library', InputArgument::REQUIRED, 'The library will be compiled');
        $this->addOption('show-libc-ver', null, null);
    }

    public function handle(): int
    {
        $library = $this->getArgument('library');
        $show_libc_ver = $this->getOption('show-libc-ver');

        $installer = new PackageInstaller(['pack-mode' => true]);
        $installer->addBuildPackage($library);

        $installer->run();

        return static::SUCCESS;
    }
}
