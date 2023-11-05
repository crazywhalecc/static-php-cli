<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\command\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('dev:php-version', 'Returns version of PHP located source directory', ['dev:php-ver'])]
class PhpVerCommand extends BaseCommand
{
    public function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->no_motd = true;
        parent::initialize($input, $output);
    }

    public function handle(): int
    {
        // Find php from source/php-src
        $file = SOURCE_PATH . '/php-src/main/php_version.h';
        if (!file_exists($file)) {
            $this->output->writeln('<error>PHP source not found, maybe you need to extract first ?</error>');

            return static::FAILURE;
        }

        $result = preg_match('/#define PHP_VERSION "([^"]+)"/', file_get_contents($file), $match);
        if ($result === false) {
            $this->output->writeln('<error>PHP source not found, maybe you need to extract first ?</error>');

            return static::FAILURE;
        }

        $this->output->writeln('<info>' . $match[1] . '</info>');
        return static::SUCCESS;
    }
}
