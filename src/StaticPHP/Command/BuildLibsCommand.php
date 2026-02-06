<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use StaticPHP\Artifact\DownloaderOptions;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Registry\PackageLoader;
use StaticPHP\Util\V2CompatLayer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('build:libs', 'Build specified library packages')]
class BuildLibsCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addArgument(
            'libraries',
            InputArgument::REQUIRED,
            'The library packages will be compiled, comma separated',
            suggestedValues: function (CompletionInput $input) {
                $packages = [];
                foreach (PackageLoader::getPackages(['target', 'library']) as $name => $_) {
                    $packages[] = $name;
                }
                $val = $input->getCompletionValue();
                return array_filter($packages, fn ($name) => str_starts_with($name, $val));
            }
        );
        // Builder options
        $this->getDefinition()->addOptions([
            new InputOption('with-suggests', ['L', 'E'], null, 'Resolve and install suggested packages as well'),
            new InputOption('with-packages', null, InputOption::VALUE_REQUIRED, 'add additional packages to install/build, comma separated', ''),
            new InputOption('no-download', null, null, 'Skip downloading artifacts (use existing cached files)'),
            ...V2CompatLayer::getLegacyBuildOptions(),
        ]);
        // Downloader options (with 'dl-' prefix to avoid conflicts)
        $this->getDefinition()->addOptions(DownloaderOptions::getConsoleOptions('dl'));
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
