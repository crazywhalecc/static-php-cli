<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\command\BaseCommand;
use SPC\exception\FileSystemException;
use SPC\exception\ValidationException;
use SPC\store\FileSystem;
use SPC\util\ConfigValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Modify config file: sort lib, ext, source by name.
 */
#[AsCommand('dev:sort-config', 'After config edited, sort it by alphabet', ['sort-config'], true)]
class SortConfigCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addArgument('config-name', InputArgument::REQUIRED, 'Your config to be sorted, you can sort "lib", "source" and "ext".');
    }

    /**
     * @throws ValidationException
     * @throws FileSystemException
     */
    public function handle(): int
    {
        switch ($name = $this->getArgument('config-name')) {
            case 'lib':
                $file = json_decode(FileSystem::readFile(ROOT_DIR . '/config/lib.json'), true);
                ConfigValidator::validateLibs($file);
                uksort($file, function ($a, $b) use ($file) {
                    $type_a = $file[$a]['type'] ?? 'lib';
                    $type_b = $file[$b]['type'] ?? 'lib';
                    $type_order = ['root', 'target', 'package', 'lib'];
                    // compare type first
                    if ($type_a !== $type_b) {
                        return array_search($type_a, $type_order) <=> array_search($type_b, $type_order);
                    }
                    // compare name
                    return $a <=> $b;
                });
                if (!file_put_contents(ROOT_DIR . '/config/lib.json', json_encode($file, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n")) {
                    $this->output->writeln('<error>Write file lib.json failed!</error>');
                    return static::FAILURE;
                }
                break;
            case 'source':
                $file = json_decode(FileSystem::readFile(ROOT_DIR . '/config/source.json'), true);
                ConfigValidator::validateSource($file);
                uksort($file, fn ($a, $b) => $a === 'php-src' ? -1 : ($b === 'php-src' ? 1 : ($a < $b ? -1 : 1)));
                if (!file_put_contents(ROOT_DIR . '/config/source.json', json_encode($file, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n")) {
                    $this->output->writeln('<error>Write file source.json failed!</error>');
                    return static::FAILURE;
                }
                break;
            case 'ext':
                $file = json_decode(FileSystem::readFile(ROOT_DIR . '/config/ext.json'), true);
                ConfigValidator::validateExts($file);
                ksort($file);
                if (!file_put_contents(ROOT_DIR . '/config/ext.json', json_encode($file, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n")) {
                    $this->output->writeln('<error>Write file ext.json failed!</error>');
                    return static::FAILURE;
                }
                break;
            case 'pkg':
                $file = json_decode(FileSystem::readFile(ROOT_DIR . '/config/pkg.json'), true);
                ConfigValidator::validatePkgs($file);
                ksort($file);
                if (!file_put_contents(ROOT_DIR . '/config/pkg.json', json_encode($file, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n")) {
                    $this->output->writeln('<error>Write file pkg.json failed!</error>');
                    return static::FAILURE;
                }
                break;
            default:
                $this->output->writeln("<error>invalid config name: {$name}</error>");
                return 1;
        }
        $this->output->writeln('<info>sort success</info>');
        return static::SUCCESS;
    }
}
