<?php

declare(strict_types=1);

namespace StaticPHP\Command\Dev;

use StaticPHP\Command\BaseCommand;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Registry\PackageLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('dev:dump-capabilities', 'Dump installable/buildable capabilities of all target and library packages')]
class DumpCapabilitiesCommand extends BaseCommand
{
    protected bool $no_motd = true;

    public function configure(): void
    {
        $this->addArgument('output', InputArgument::OPTIONAL, 'Output file path (JSON). Defaults to <ROOT_DIR>/dump-capabilities.json', ROOT_DIR . '/dump-capabilities.json');
        $this->addOption('print', null, InputOption::VALUE_NONE, 'Print capabilities as a table to the terminal instead of writing to a file');
    }

    public function handle(): int
    {
        $result = $this->buildCapabilities();

        if ($this->getOption('print')) {
            $this->printTable($result);
        } else {
            $outputFile = $this->getArgument('output');
            $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            file_put_contents($outputFile, $json . PHP_EOL);
            $this->output->writeln('<info>Dumped capabilities for ' . count($result) . " package(s) to: {$outputFile}</info>");
        }

        return static::SUCCESS;
    }

    /**
     * Build the capabilities map for all relevant packages.
     *
     * For library/target/virtual-target:
     *   buildable: string[]  - OS families with a registered #[BuildFor] function
     *   installable: string[] - arch-os platforms with a declared binary
     *
     * For php-extension:
     *   buildable: array<string, string>  - {OS: 'yes'|'wip'|'partial'|'no'} (v2 support semantics)
     *   installable: (not applicable, omitted)
     */
    private function buildCapabilities(): array
    {
        $result = [];

        // library / target / virtual-target
        foreach (PackageLoader::getPackages(['library', 'target', 'virtual-target']) as $name => $pkg) {
            $installable = [];
            $artifact = $pkg->getArtifact();
            if ($artifact !== null) {
                $installable = $artifact->getBinaryPlatforms();
            }

            $result[$name] = [
                'type' => $pkg->getType(),
                'buildable' => $pkg->getBuildForOSList(),
                'installable' => $installable,
            ];
        }

        // php-extension: buildable uses v2 support-field semantics
        foreach (PackageLoader::getPackages('php-extension') as $name => $pkg) {
            /* @var PhpExtensionPackage $pkg */
            $result[$name] = [
                'type' => $pkg->getType(),
                'buildable' => $pkg->getBuildSupportStatus(),
            ];
        }

        return $result;
    }

    private function printTable(array $result): void
    {
        $table = new Table($this->output);
        $table->setHeaders(['Package', 'Type', 'Buildable (OS)', 'Installable (arch-os)']);

        foreach ($result as $name => $info) {
            // For php-extension, buildable is a map {OS => status}
            if (is_array($info['buildable']) && array_is_list($info['buildable']) === false) {
                $buildableStr = implode("\n", array_map(
                    static fn (string $os, string $status) => $status === 'yes' ? $os : "{$os} ({$status})",
                    array_keys($info['buildable']),
                    array_values($info['buildable'])
                ));
            } else {
                $buildableStr = implode("\n", $info['buildable']) ?: '<none>';
            }

            $table->addRow([
                $name,
                $info['type'],
                $buildableStr,
                implode("\n", $info['installable'] ?? []) ?: '<n/a>',
            ]);
        }

        $table->render();
    }
}
