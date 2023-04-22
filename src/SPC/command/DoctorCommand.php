<?php

declare(strict_types=1);

namespace SPC\command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('doctor', 'Diagnose whether the current environment can compile normally')]
class DoctorCommand extends BaseCommand
{
    public function handle(): int
    {
        logger()->error('Not implemented');
        return 1;
    }
}
