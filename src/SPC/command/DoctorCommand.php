<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\doctor\CheckResult;
use SPC\doctor\DoctorHandler;
use SPC\util\AttributeMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use ZM\Logger\ConsoleColor;

use function Laravel\Prompts\confirm;

#[AsCommand('doctor', 'Diagnose whether the current environment can compile normally')]
class DoctorCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addOption('auto-fix', null, InputOption::VALUE_OPTIONAL, 'Automatically fix failed items (if possible)', false);
    }

    public function handle(): int
    {
        $fix_policy = match ($this->input->getOption('auto-fix')) {
            'never' => FIX_POLICY_DIE,
            true, null => FIX_POLICY_AUTOFIX,
            default => FIX_POLICY_PROMPT,
        };
        $fix_map = AttributeMapper::getDoctorFixMap();

        foreach (DoctorHandler::getValidCheckList() as $check) {
            // output
            $this->output->write("Checking <comment>{$check->item_name}</comment> ... ");

            // null => skipped
            if (($result = call_user_func($check->callback)) === null) {
                $this->output->writeln('skipped');
                continue;
            }
            // invalid return value => skipped
            if (!$result instanceof CheckResult) {
                $this->output->writeln('<error>Skipped due to invalid return value</error>');
                continue;
            }
            // true => OK
            if ($result->isOK()) {
                /* @phpstan-ignore-next-line */
                $this->output->writeln($result->getMessage() ?? (string) ConsoleColor::green('✓'));
                continue;
            }

            // Failed => output error message
            $this->output->writeln('<error>' . $result->getMessage() . '</error>');
            // If the result is not fixable, fail immediately
            if ($result->getFixItem() === '') {
                $this->output->writeln('This check item can not be fixed !');
                return static::FAILURE;
            }
            if (!isset($fix_map[$result->getFixItem()])) {
                $this->output->writeln("<error>Internal error: Unknown fix item: {$result->getFixItem()}</error>");
                return static::FAILURE;
            }

            // prompt for fix
            if ($fix_policy === FIX_POLICY_PROMPT) {
                if (!confirm('Do you want to fix it?')) {
                    $this->output->writeln('<comment>You canceled fix.</comment>');
                    return static::FAILURE;
                }
                if (DoctorHandler::emitFix($this->output, $result)) {
                    $this->output->writeln('<info>Fix applied successfully!</info>');
                } else {
                    $this->output->writeln('<error>Failed to apply fix!</error>');
                    return static::FAILURE;
                }
            }

            // auto fix
            if ($fix_policy === FIX_POLICY_AUTOFIX) {
                $this->output->writeln('Automatically fixing ' . $result->getFixItem() . ' ...');
                if (DoctorHandler::emitFix($this->output, $result)) {
                    $this->output->writeln('<info>Fix applied successfully!</info>');
                } else {
                    $this->output->writeln('<error>Failed to apply fix!</error>');
                    return static::FAILURE;
                }
            }
        }

        $this->output->writeln('<info>Doctor check complete !</info>');
        return static::SUCCESS;
    }
}
