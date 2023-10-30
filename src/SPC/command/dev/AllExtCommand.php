<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\command\BaseCommand;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\util\DependencyUtil;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Laravel\Prompts\table;

#[AsCommand('dev:extensions', 'Helper command that lists available extension details', ['list-ext'])]
class AllExtCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addArgument('extensions', InputArgument::OPTIONAL, 'List of extensions that will be displayed, comma separated');
    }

    /**
     * @throws FileSystemException
     * @throws WrongUsageException
     * @throws RuntimeException
     */
    public function handle(): int
    {
        $extensions = array_map('trim', array_filter(explode(',', $this->getArgument('extensions') ?? '')));

        $style = new SymfonyStyle($this->input, $this->output);
        $style->writeln($extensions ? 'Available extensions:' : 'Extensions:');

        $data = [];
        foreach (Config::getExts() as $extension => $details) {
            if ($extensions !== [] && !\in_array($extension, $extensions, true)) {
                continue;
            }

            try {
                [, $libraries, $not_included] = DependencyUtil::getExtLibsByDeps([$extension]);
            } catch (WrongUsageException) {
                $libraries = $not_included = [];
            }

            $lib_suggests = Config::getExt($extension, 'lib-suggests', []);
            $ext_suggests = Config::getExt($extension, 'ext-suggests', []);

            $data[] = [
                $extension,
                implode(', ', $libraries),
                implode(', ', $lib_suggests),
                implode(',', $not_included),
                implode(', ', $ext_suggests),
                Config::getExt($extension, 'unix-only', false) ? 'true' : 'false',
            ];
        }

        if ($data === []) {
            $style->warning('Unknown extension selected: ' . implode(',', $extensions));
        } else {
            table(
                ['Extension', 'lib-depends', 'lib-suggests', 'ext-depends', 'ext-suggests', 'unix-only'],
                $data
            );
        }

        return static::SUCCESS;
    }
}
