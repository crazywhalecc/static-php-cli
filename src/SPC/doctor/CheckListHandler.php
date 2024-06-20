<?php

declare(strict_types=1);

namespace SPC\doctor;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckListHandler
{
    /** @var AsCheckItem[] */
    private array $check_list = [];

    private array $fix_map = [];

    public function __construct() {}

    /**
     * @return array<AsCheckItem>
     * @throws \ReflectionException
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function runChecks(bool $include_manual = false): array
    {
        return $this->loadCheckList($include_manual);
    }

    /**
     * @throws RuntimeException
     */
    public function emitFix(OutputInterface $output, CheckResult $result): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            sapi_windows_set_ctrl_handler(function () use ($output) {
                $output->writeln('<error>You cancelled fix</error>');
            });
        } elseif (extension_loaded('pcntl')) {
            pcntl_signal(SIGINT, function () use ($output) {
                $output->writeln('<error>You cancelled fix</error>');
            });
        }

        $fix_result = call_user_func($this->fix_map[$result->getFixItem()], ...$result->getFixParams());

        if (PHP_OS_FAMILY === 'Windows') {
            sapi_windows_set_ctrl_handler(null);
        } elseif (extension_loaded('pcntl')) {
            pcntl_signal(SIGINT, SIG_IGN);
        }

        if ($fix_result) {
            $output->writeln('<info>Fix done</info>');
        } else {
            $output->writeln('<error>Fix failed</error>');
            throw new RuntimeException('Some check item are not fixed');
        }
    }

    /**
     * Load Doctor check item list
     *
     * @return array<AsCheckItem>
     * @throws \ReflectionException
     * @throws RuntimeException
     * @throws FileSystemException
     */
    private function loadCheckList(bool $include_manual = false): array
    {
        foreach (FileSystem::getClassesPsr4(__DIR__ . '/item', 'SPC\doctor\item') as $class) {
            $ref = new \ReflectionClass($class);
            foreach ($ref->getMethods() as $method) {
                foreach ($method->getAttributes() as $a) {
                    if (is_a($a->getName(), AsCheckItem::class, true)) {
                        /** @var AsCheckItem $instance */
                        $instance = $a->newInstance();
                        if (!$include_manual && $instance->manual) {
                            continue;
                        }
                        $instance->callback = [new $class(), $method->getName()];
                        $this->check_list[] = $instance;
                    } elseif (is_a($a->getName(), AsFixItem::class, true)) {
                        /** @var AsFixItem $instance */
                        $instance = $a->newInstance();
                        // Redundant fix item
                        if (isset($this->fix_map[$instance->name])) {
                            throw new RuntimeException('Redundant doctor fix item: ' . $instance->name);
                        }
                        $this->fix_map[$instance->name] = [new $class(), $method->getName()];
                    }
                }
            }
        }

        // sort check list by level
        usort($this->check_list, fn (AsCheckItem $a, AsCheckItem $b) => $a->level > $b->level ? -1 : ($a->level == $b->level ? 0 : 1));

        return $this->check_list;
    }
}
