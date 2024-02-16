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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('dev:extensions', 'Helper command that lists available extension details', ['list-ext'])]
class AllExtCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addArgument('extensions', InputArgument::OPTIONAL, 'List of extensions that will be displayed, comma separated');
        $this->addOption(
            'columns',
            null,
            InputOption::VALUE_REQUIRED,
            'List of columns that will be displayed, comma separated (lib-depends, lib-suggests, ext-depends, ext-suggests, unix-only)',
            'lib-depends,lib-suggests,ext-depends,ext-suggests,unix-only'
        );
    }

    /**
     * @throws FileSystemException
     * @throws WrongUsageException
     * @throws RuntimeException
     */
    public function handle(): int
    {
        $extensions = array_map('trim', array_filter(explode(',', $this->getArgument('extensions') ?? '')));
        $columns = array_map('trim', array_filter(explode(',', $this->getOption('columns'))));

        foreach ($columns as $column) {
            if (!in_array($column, ['lib-depends', 'lib-suggests', 'ext-depends', 'ext-suggests', 'unix-only', 'type'])) {
                $this->output->writeln('<error>Column name [' . $column . '] is not valid.</error>');
                $this->output->writeln('<error>Available column name: lib-depends, lib-suggests, ext-depends, ext-suggests, unix-only, type</error>');
                return static::FAILURE;
            }
        }
        array_unshift($columns, 'name');

        $style = new SymfonyStyle($this->input, $this->output);
        $style->writeln($extensions ? 'Available extensions:' : 'Extensions:');

        $data = [];
        foreach (Config::getExts() as $extension => $details) {
            if ($extensions !== [] && !\in_array($extension, $extensions, true)) {
                continue;
            }

            try {
                [, $libraries, $not_included] = DependencyUtil::getExtsAndLibs([$extension]);
            } catch (WrongUsageException) {
                $libraries = $not_included = [];
            }

            $lib_suggests = Config::getExt($extension, 'lib-suggests', []);
            $ext_suggests = Config::getExt($extension, 'ext-suggests', []);

            $row = [];
            foreach ($columns as $column) {
                $row[] = match ($column) {
                    'name' => $extension,
                    'type' => Config::getExt($extension, 'type'),
                    'lib-depends' => implode(', ', $libraries),
                    'lib-suggests' => implode(', ', $lib_suggests),
                    'ext-depends' => implode(',', $not_included),
                    'ext-suggests' => implode(', ', $ext_suggests),
                    'unix-only' => Config::getExt($extension, 'unix-only', false) ? 'true' : 'false',
                    default => '',
                };
            }
            $data[] = $row;
        }

        if ($data === []) {
            $style->warning('Unknown extension selected: ' . implode(',', $extensions));
        } else {
            $func = PHP_OS_FAMILY === 'Windows' ? [$style, 'table'] : '\Laravel\Prompts\table';
            call_user_func($func, $columns, $data);
        }

        return static::SUCCESS;
    }
}
