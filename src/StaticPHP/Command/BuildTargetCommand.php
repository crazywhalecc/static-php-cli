<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use StaticPHP\Artifact\DownloaderOptions;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Registry\PackageLoader;
use StaticPHP\Util\V2CompatLayer;
use Symfony\Component\Console\Input\InputOption;

class BuildTargetCommand extends BaseCommand
{
    public function __construct(private readonly string $target, ?string $description = null)
    {
        parent::__construct("build:{$target}");
        if ($target === 'php') {
            $this->setAliases(['build']);
        }
        $this->setDescription($description ?? "Build {$target} target from source");
        $pkg = PackageLoader::getTargetPackage($target);
        $this->getDefinition()->addOptions($pkg->_exportBuildOptions());
        $this->getDefinition()->addArguments($pkg->_exportBuildArguments());

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
        // resolve legacy options to new options
        V2CompatLayer::convertOptions($this->input);

        $starttime = microtime(true);
        // run installer
        $installer = new PackageInstaller($this->input->getOptions());
        $installer->addBuildPackage($this->target);
        $installer->run();

        $usedtime = round(microtime(true) - $starttime, 1);
        $this->output->writeln("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->output->writeln("<info>✔ BUILD SUCCESSFUL ({$usedtime} s)</info>");
        $this->output->writeln("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

        $installer->printBuildPackageOutputs();

        return static::SUCCESS;
    }
}
