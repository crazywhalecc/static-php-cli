<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use StaticPHP\Doctor\Doctor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('doctor', 'Diagnose whether the current environment can compile normally')]
class DoctorCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addOption('auto-fix', null, InputOption::VALUE_OPTIONAL, 'Automatically fix failed items (if possible)', false);
    }

    public function handle(): int
    {
        f_putenv('SPC_SKIP_TOOLCHAIN_CHECK=yes');
        $fix_policy = match ($this->input->getOption('auto-fix')) {
            'never' => FIX_POLICY_DIE,
            true, null => FIX_POLICY_AUTOFIX,
            default => FIX_POLICY_PROMPT,
        };
        $doctor = new Doctor($this->output, $fix_policy);
        if ($doctor->checkAll()) {
            $this->output->writeln('<info>Doctor check complete !</info>');
            return static::SUCCESS;
        }

        return static::ENVIRONMENT_ERROR;
    }
}
