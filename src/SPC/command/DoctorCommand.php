<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\doctor\CheckListHandler;
use SPC\doctor\CheckResult;
use SPC\exception\RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;

#[AsCommand('doctor', 'Diagnose whether the current environment can compile normally')]
class DoctorCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addOption('auto-fix', null, null, 'Automatically fix failed items (if possible)');
    }

    public function handle(): int
    {
        try {
            $checker = new CheckListHandler();
            // skipped items
            $skip_items = array_filter(explode(',', getenv('SPC_SKIP_DOCTOR_CHECK_ITEMS') ?: ''));

            $fix_policy = $this->input->getOption('auto-fix') ? FIX_POLICY_AUTOFIX : FIX_POLICY_PROMPT;
            foreach ($checker->runChecks() as $check) {
                if ($check->limit_os !== null && $check->limit_os !== PHP_OS_FAMILY) {
                    continue;
                }

                $this->output->write('Checking <comment>' . $check->item_name . '</comment> ... ');

                // check if this item is skipped
                if (in_array($check->item_name, $skip_items) || ($result = call_user_func($check->callback)) === null) {
                    $this->output->writeln('skipped');
                } elseif ($result instanceof CheckResult) {
                    if ($result->isOK()) {
                        $this->output->writeln($result->getMessage() ?? 'ok');
                        continue;
                    }

                    // Failed
                    $this->output->writeln('<error>' . $result->getMessage() . '</error>');
                    switch ($fix_policy) {
                        case FIX_POLICY_DIE:
                            throw new RuntimeException('Some check items can not be fixed !');
                        case FIX_POLICY_PROMPT:
                            if ($result->getFixItem() !== '') {
                                $question = confirm('Do you want to fix it?');
                                if ($question) {
                                    $checker->emitFix($this->output, $result);
                                } else {
                                    throw new RuntimeException('You cancelled fix');
                                }
                            } else {
                                throw new RuntimeException('Some check items can not be fixed !');
                            }
                            break;
                        case FIX_POLICY_AUTOFIX:
                            if ($result->getFixItem() !== '') {
                                $this->output->writeln('Automatically fixing ' . $result->getFixItem() . ' ...');
                                $checker->emitFix($this->output, $result);
                            } else {
                                throw new RuntimeException('Some check items can not be fixed !');
                            }
                            break;
                    }
                }
            }

            $this->output->writeln('<info>Doctor check complete !</info>');
        } catch (\Throwable $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');

            if (extension_loaded('pcntl')) {
                pcntl_signal(SIGINT, SIG_IGN);
            }
            return static::FAILURE;
        }

        return static::SUCCESS;
    }
}
