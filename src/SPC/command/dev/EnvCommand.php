<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\command\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('dev:env', 'Returns the internally defined environment variables')]
class EnvCommand extends BaseCommand
{
    public function configure()
    {
        $this->addArgument('env', InputArgument::REQUIRED, 'The environment variable to show, if not set, all will be shown');
    }

    public function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->no_motd = true;
        parent::initialize($input, $output);
    }

    public function handle(): int
    {
        $env = $this->getArgument('env');
        if (($val = getenv($env)) === false) {
            $this->output->writeln("<error>Environment variable '{$env}' is not set.</error>");
            return static::FAILURE;
        }
        $this->output->writeln("<info>{$val}</info>");
        return static::SUCCESS;
    }
}
