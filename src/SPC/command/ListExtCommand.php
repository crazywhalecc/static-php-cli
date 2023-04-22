<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\builder\traits\NoMotdTrait;
use SPC\exception\FileSystemException;
use SPC\store\Config;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('list-ext', 'List supported extensions')]
class ListExtCommand extends BaseCommand
{
    use NoMotdTrait;

    /**
     * @throws FileSystemException
     */
    public function handle(): int
    {
        foreach (Config::getExts() as $ext => $meta) {
            echo $ext . PHP_EOL;
        }
        return 0;
    }
}
