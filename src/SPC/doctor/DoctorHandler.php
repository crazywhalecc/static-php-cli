<?php

declare(strict_types=1);

namespace SPC\doctor;

use SPC\exception\SPCException;
use SPC\util\AttributeMapper;
use Symfony\Component\Console\Output\OutputInterface;

final class DoctorHandler
{
    /**
     * Returns a list of valid check items.
     *
     * @return array<AsCheckItem>
     */
    public static function getValidCheckList(): iterable
    {
        foreach (AttributeMapper::getDoctorCheckMap() as [$item, $optional]) {
            /* @var AsCheckItem $item */
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

    /**
     * Emit the fix for a given CheckResult.
     *
     * @param  OutputInterface $output the output interface to write messages to
     * @param  CheckResult     $result the result of the check that needs fixing
     * @return bool            returns true if the fix was successful, false otherwise
     */
    public static function emitFix(OutputInterface $output, CheckResult $result): bool
    {
        keyboard_interrupt_register(function () use ($output) {
            $output->writeln('<error>You cancelled fix</error>');
        });
        try {
            $fix_result = call_user_func(AttributeMapper::getDoctorFixMap()[$result->getFixItem()], ...$result->getFixParams());
        } catch (SPCException $e) {
            $output->writeln('<error>Fix failed: ' . $e->getMessage() . '</error>');
            return false;
        } catch (\Throwable $e) {
            $output->writeln('<error>Fix failed with an unexpected error: ' . $e->getMessage() . '</error>');
            return false;
        }
        keyboard_interrupt_unregister();
        return $fix_result;
    }
}
