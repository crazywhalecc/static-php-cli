<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\doctor\CheckListHandler;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('doctor', 'Diagnose whether the current environment can compile normally')]
class DoctorCommand extends BaseCommand
{
    public function configure()
    {
        $this->addOption('auto-fix', null, null, 'Automatically fix failed items (if possible)');
    }

    public function handle(): int
    {
        try {
            $checker = new CheckListHandler($this->input, $this->output);
            $checker->runCheck($this->input->getOption('auto-fix') ? FIX_POLICY_AUTOFIX : FIX_POLICY_PROMPT);
            $this->output->writeln('<info>Doctor check complete !</info>');
        } catch (\Throwable $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
            pcntl_signal(SIGINT, SIG_IGN);
            return 1;
        }
        return 0;
    }
}
