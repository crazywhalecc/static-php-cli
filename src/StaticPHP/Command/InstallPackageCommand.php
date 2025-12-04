<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use StaticPHP\DI\ApplicationContext;
use StaticPHP\Package\PackageInstaller;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('install-pkg', 'Install additional packages', ['i', 'install-package'])]
class InstallPackageCommand extends BaseCommand
{
    public function configure()
    {
        $this->addArgument('packages', null, 'The package to install (name or path)');
    }

    public function handle(): int
    {
        ApplicationContext::set('elephant', true);
        $installer = new PackageInstaller([...$this->input->getOptions(), 'dl-prefer-binary' => true]);
        $installer->addInstallPackage($this->input->getArgument('packages'));
        $installer->run(true, true);
        return static::SUCCESS;
    }
}
