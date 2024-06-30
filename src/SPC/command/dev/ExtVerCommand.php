<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\builder\BuilderProvider;
use SPC\command\BaseCommand;
use SPC\store\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('dev:ext-version', 'Returns version of extension from source directory', ['dev:ext-ver'])]
class ExtVerCommand extends BaseCommand
{
    public function configure()
    {
        $this->addArgument('extension', InputArgument::REQUIRED, 'The library name');
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

        $ext_conf = Config::getExt($this->getArgument('extension'));
        $builder->proveExts([$this->getArgument('extension')], true);

        // Check whether lib is extracted
        // if (!is_dir(SOURCE_PATH . '/' . $this->getArgument('library'))) {
        //     $this->output->writeln("<error>Library {$this->getArgument('library')} is not extracted</error>");
        //     return static::FAILURE;
        // }

        $version = $builder->getExt($this->getArgument('extension'))->getExtVersion();
        if ($version === null) {
            $this->output->writeln("<error>Failed to get version of extension {$this->getArgument('extension')}</error>");
            return static::FAILURE;
        }
        $this->output->writeln("<info>{$version}</info>");
        return static::SUCCESS;
    }
}
