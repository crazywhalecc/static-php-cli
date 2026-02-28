<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Exception\SPCException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('check-update', description: 'Check for updates for a specific artifact')]
class CheckUpdateCommand extends BaseCommand
{
    public bool $no_motd = true;

    public function configure(): void
    {
        $this->addArgument('artifact', InputArgument::REQUIRED, 'The name of the artifact(s) to check for updates, comma-separated');
        $this->addOption('json', null, null, 'Output result in JSON format');
        $this->addOption('bare', null, null, 'Check update without requiring the artifact to be downloaded first (old version will be null)');

        // --with-php option for checking updates with a specific PHP version context
        $this->addOption('with-php', null, InputOption::VALUE_REQUIRED, 'PHP version in major.minor format (default 8.4)', '8.4');
    }

    public function handle(): int
    {
        $artifacts = parse_comma_list($this->input->getArgument('artifact'));

        try {
            $downloader = new ArtifactDownloader($this->input->getOptions());
            $bare = (bool) $this->getOption('bare');
            if ($this->getOption('json')) {
                $outputs = [];
                foreach ($artifacts as $artifact) {
                    $result = $downloader->checkUpdate($artifact, bare: $bare);
                    $outputs[$artifact] = [
                        'need-update' => $result->needUpdate,
                        'old' => $result->old,
                        'new' => $result->new,
                    ];
                }
                $this->output->writeln(json_encode($outputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                return static::OK;
            }
            foreach ($artifacts as $artifact) {
                $result = $downloader->checkUpdate($artifact, bare: $bare);
                if (!$result->needUpdate) {
                    $this->output->writeln("Artifact <info>{$artifact}</info> is already up to date (version: {$result->new})");
                } else {
                    $this->output->writeln("<comment>Update available for artifact: {$artifact}</comment>");
                    [$old, $new] = [$result->old ?? 'unavailable', $result->new ?? 'unknown'];
                    $this->output->writeln("  Old version: <error>{$old}</error>");
                    $this->output->writeln("  New version: <info>{$new}</info>");
                }
            }
            return static::OK;
        } catch (SPCException $e) {
            $e->setSimpleOutput();
            throw $e;
        }
    }
}
