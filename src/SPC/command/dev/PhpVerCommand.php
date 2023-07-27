<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\command\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('dev:php-ver', 'Dev command')]
class PhpVerCommand extends BaseCommand
{
    public function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->no_motd = true;
        parent::initialize($input, $output);
    }

    public function handle(): int
    {
        // Find php from source/php-src
        $file = SOURCE_PATH . '/php-src/main/php_version.h';
        $result = preg_match('/#define PHP_VERSION "(\d+\.\d+\.\d+)"/', file_get_contents($file), $match);
        if ($result === false) {
            $this->output->writeln('<error>PHP source not found, maybe you need to extract first ?</error>');
            return 1;
        }
        $this->output->writeln('<info>' . $match[1] . '</info>');
        return 0;
    }
}
