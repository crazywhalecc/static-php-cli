<?php

declare(strict_types=1);

namespace StaticPHP\Doctor;

use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\SPCException;
use StaticPHP\Runtime\Shell\Shell;
use StaticPHP\Util\InteractiveTerm;
use Symfony\Component\Console\Output\OutputInterface;
use ZM\Logger\ConsoleColor;

use function Laravel\Prompts\confirm;

readonly class Doctor
{
    public function __construct(private ?OutputInterface $output = null, private int $auto_fix = FIX_POLICY_PROMPT)
    {
        // debug shows all loaded doctor items
        $items = DoctorLoader::getDoctorItems();
        $names = array_map(fn ($i) => $i->item_name, array_map(fn ($x) => $x[0], $items));
        logger()->debug("Loaded doctor check items:\n\t" . implode("\n\t", $names));
    }

    /**
     * Check all valid check items.
     * @return bool true if all checks passed, false otherwise
     */
    public function checkAll(bool $interactive = true): bool
    {
        if ($interactive) {
            InteractiveTerm::notice('Starting doctor checks ...');
        }
        foreach ($this->getValidCheckList() as $check) {
            if (!$this->checkItem($check, $interactive)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check a single check item.
     *
     * @param  CheckItem|string $check The check item to be checked
     * @return bool             True if the check passed or was fixed, false otherwise
     */
    public function checkItem(CheckItem|string $check, bool $interactive = true): bool
    {
        if (is_string($check)) {
            $found = null;
            foreach (DoctorLoader::getDoctorItems() as $item) {
                if ($item[0]->item_name === $check) {
                    $found = $item[0];
                    break;
                }
            }
            if ($found === null) {
                $this->output?->writeln("<error>Check item '{$check}' not found.</error>");
                return false;
            }
            $check = $found;
        }
        $prepend = $interactive ? '  - ' : '';
        $this->output?->write("{$prepend}Checking <comment>{$check->item_name}</comment> ... ");

        // call check
        $result = call_user_func($check->callback);

        if ($result === null) {
            $this->output?->writeln('skipped');
            return true;
        }
        if (!$result instanceof CheckResult) {
            $this->output?->writeln('<error>Skipped due to invalid return value</error>');
            return true;
        }
        if ($result->isOK()) {
            /* @phpstan-ignore-next-line */
            $this->output?->writeln($result->getMessage() ?? (string) ConsoleColor::green('✓'));
            return true;
        }
        $this->output?->writeln('<error>' . $result->getMessage() . '</error>');

        // if the check item is not fixable, fail immediately
        if ($result->getFixItem() === '') {
            $this->output?->writeln('This check item can not be fixed automatically !');
            return false;
        }
        // unknown fix item
        if (!DoctorLoader::getFixItem($result->getFixItem())) {
            $this->output?->writeln("<error>Internal error: Unknown fix item: {$result->getFixItem()}</error>");
            return false;
        }
        // skip fix
        if ($this->auto_fix === FIX_POLICY_DIE) {
            $this->output?->writeln('<comment>Auto-fix is disabled. Please fix this issue manually.</comment>');
            return false;
        }
        // prompt for fix
        if ($this->auto_fix === FIX_POLICY_PROMPT && !confirm('Do you want to try to fix this issue now?')) {
            $this->output?->writeln('<comment>You canceled fix.</comment>');
            return false;
        }
        // perform fix
        InteractiveTerm::indicateProgress("Fixing {$result->getFixItem()} ... ");
        Shell::passthruCallback(function () {
            InteractiveTerm::advance();
        });
        // $this->output?->writeln("Fixing <comment>{$check->item_name}</comment> ... ");
        if ($this->emitFix($result->getFixItem(), $result->getFixParams())) {
            InteractiveTerm::finish('Fix applied successfully!');
            return true;
        }
        InteractiveTerm::finish('Failed to apply fix!', false);
        return false;
    }

    private function emitFix(string $fix_item, array $fix_item_params = []): bool
    {
        keyboard_interrupt_register(function () {
            $this->output?->writeln('<error>You cancelled fix</error>');
        });
        try {
            return ApplicationContext::invoke(DoctorLoader::getFixItem($fix_item), $fix_item_params);
        } catch (SPCException $e) {
            $this->output?->writeln('<error>Fix failed: ' . $e->getMessage() . '</error>');
            return false;
        } catch (\Throwable $e) {
            $this->output?->writeln('<error>Fix failed with an unexpected error: ' . $e->getMessage() . '</error>');
            return false;
        } finally {
            keyboard_interrupt_unregister();
        }
    }

    /**
     * Get a list of valid check items for current environment.
     */
    private function getValidCheckList(): iterable
    {
        foreach (DoctorLoader::getDoctorItems() as [$item, $optional]) {
            /* @var CheckItem $item */
            // optional check
            if ($optional !== null && !call_user_func($optional)) {
                continue; // skip this when the optional check is false
            }
            // limit_os check
            if ($item->limit_os !== null && $item->limit_os !== PHP_OS_FAMILY) {
                continue;
            }
            // skipped items by env
            $skip_items = array_filter(explode(',', getenv('SPC_SKIP_DOCTOR_CHECK_ITEMS') ?: ''));
            if (in_array($item->item_name, $skip_items)) {
                continue; // skip this item
            }
            yield $item;
        }
    }
}
