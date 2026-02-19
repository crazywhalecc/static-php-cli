<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use StaticPHP\DI\ApplicationContext;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Registry\PackageLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('install-pkg', 'Install additional package', ['i', 'install-package'])]
class InstallPackageCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addArgument(
            'package',
            InputArgument::REQUIRED,
            'The package to install (name or path)',
            suggestedValues: function (CompletionInput $input) {
                $packages = [];
                foreach (PackageLoader::getPackages(['target', 'virtual-target']) as $name => $_) {
                    $packages[] = $name;
                }
                $val = $input->getCompletionValue();
                return array_filter($packages, fn ($name) => str_starts_with($name, $val));
            }
        );
    }

    public function handle(): int
    {
        ApplicationContext::set('elephant', true);
        $installer = new PackageInstaller([...$this->input->getOptions(), 'dl-prefer-binary' => true]);
        $installer->addInstallPackage($this->input->getArgument('package'));
        $installer->run(true, true);
        return static::SUCCESS;
    }
}
