<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\builder\BuilderProvider;
use SPC\command\BaseCommand;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('dev:lib-version', 'Returns version of library from source directory', ['dev:lib-ver'])]
class LibVerCommand extends BaseCommand
{
    public function configure()
    {
        $this->addArgument('library', InputArgument::REQUIRED, 'The library name');
    }

    public function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->no_motd = true;
        parent::initialize($input, $output);
    }

    public function handle(): int
    {
        // Get lib object
        $builder = BuilderProvider::makeBuilderByInput($this->input);
        $builder->setLibsOnly();

        // check lib name exist in lib.json
        try {
            Config::getLib($this->getArgument('library'));
        } catch (WrongUsageException $e) {
            $this->output->writeln("<error>Library {$this->getArgument('library')} is not supported yet</error>");
            return static::FAILURE;
        }

        $builder->proveLibs([$this->getArgument('library')]);

        // Check whether lib is extracted
        if (!is_dir(SOURCE_PATH . '/' . $this->getArgument('library'))) {
            $this->output->writeln("<error>Library {$this->getArgument('library')} is not extracted</error>");
            return static::FAILURE;
        }

        $version = $builder->getLib($this->getArgument('library'))->getLibVersion();
        if ($version === null) {
            $this->output->writeln("<error>Failed to get version of library {$this->getArgument('library')}</error>");
            return static::FAILURE;
        }
        $this->output->writeln("<info>{$version}</info>");
        return static::SUCCESS;
    }
}
