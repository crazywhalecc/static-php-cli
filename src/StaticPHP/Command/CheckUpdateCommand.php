<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\Type\CheckUpdateResult;
use StaticPHP\DI\ApplicationContext;
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
        $this->addArgument('artifact', InputArgument::OPTIONAL, 'The name of the artifact(s) to check for updates, comma-separated (default: all downloaded artifacts)');
        $this->addOption('json', null, null, 'Output result in JSON format');
        $this->addOption('bare', null, null, 'Check update without requiring the artifact to be downloaded first (old version will be null)');
        $this->addOption('parallel', 'p', InputOption::VALUE_REQUIRED, 'Number of parallel update checks (default: 10)', 10);

        // --with-php option for checking updates with a specific PHP version context
        $this->addOption('with-php', null, InputOption::VALUE_REQUIRED, 'PHP version in major.minor format (default 8.4)', '8.4');
    }

    public function handle(): int
    {
        $artifact_arg = $this->input->getArgument('artifact');
        if ($artifact_arg === null) {
            $artifacts = ApplicationContext::get(ArtifactCache::class)->getCachedArtifactNames();
            if (empty($artifacts)) {
                $this->output->writeln('<comment>No downloaded artifacts found.</comment>');
                return static::OK;
            }
        } else {
            $artifacts = parse_comma_list($artifact_arg);
        }

        try {
            $downloader = new ArtifactDownloader($this->input->getOptions());
            $bare = (bool) $this->getOption('bare');
            if ($this->getOption('json')) {
                $results = $downloader->checkUpdates($artifacts, bare: $bare);
                $outputs = [];
                foreach ($results as $artifact => $result) {
                    $outputs[$artifact] = [
                        'need-update' => $result->needUpdate,
                        'unsupported' => $result->unsupported,
                        'old' => $result->old,
                        'new' => $result->new,
                    ];
                }
                $this->output->writeln(json_encode($outputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                return static::OK;
            }
            $downloader->checkUpdates($artifacts, bare: $bare, onResult: function (string $artifact, CheckUpdateResult $result) {
                if ($result->unsupported) {
                    $this->output->writeln("Artifact <info>{$artifact}</info> does not support update checking, <comment>skipped</comment>");
                } elseif (!$result->needUpdate) {
                    $ver = $result->new ? "(<comment>{$result->new}</comment>)" : '';
                    $this->output->writeln("Artifact <info>{$artifact}</info> is already up to date {$ver}");
                } else {
                    [$old, $new] = [$result->old ?? 'unavailable', $result->new ?? 'unknown'];
                    $this->output->writeln("Update available for <info>{$artifact}</info>: <comment>{$old}</comment> -> <comment>{$new}</comment>");
                }
            });
            return static::OK;
        } catch (SPCException $e) {
            $e->setSimpleOutput();
            throw $e;
        }
    }
}
