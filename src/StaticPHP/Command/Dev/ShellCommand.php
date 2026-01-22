<?php

declare(strict_types=1);

namespace StaticPHP\Command\Dev;

use StaticPHP\Command\BaseCommand;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\GlobalEnvManager;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('dev:shell')]
class ShellCommand extends BaseCommand
{
    public function handle(): int
    {
        // need to init global env first
        GlobalEnvManager::afterInit();

        $this->output->writeln("Entering interactive shell. Type 'exit' to leave.");

        if (SystemTarget::isUnix()) {
            passthru('PS1=\'[StaticPHP] > \' /bin/bash', $code);
            return $code;
        }
        if (SystemTarget::getTargetOS() === 'Windows') {
            passthru('cmd.exe', $code);
            return $code;
        }
        $this->output->writeln('<error>Unsupported OS for shell command.</error>');
        return static::FAILURE;
    }
}
