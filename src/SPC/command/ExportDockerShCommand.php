<?php

declare(strict_types=1);

namespace SPC\command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('export-docker-sh', 'Export spc-alpine-docker file')]
class ExportDockerShCommand extends BaseCommand
{
    public function configure()
    {
        $this->no_motd = true;
    }

    public function handle(): int
    {
        $file = file_get_contents(ROOT_DIR . '/bin/spc-alpine-docker');
        if (\Phar::running() !== '') {
            $file = str_replace('$SPC_CALL_FROM_PHAR', \Phar::running(false), $file);
        }
        echo $file;
        return static::SUCCESS;
    }
}
