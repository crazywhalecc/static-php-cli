<?php

declare(strict_types=1);

namespace SPC\command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'dump-extensions', description: 'Überprüft die benötigten PHP-Extensions')]
class DumpExtensionsCommand extends BaseCommand
{
    private array $files = [
        'vendor/composer/installed.json',
        'composer.lock',
        'composer.json',
    ];

    public function handle(): int
    {
        $fs = new Filesystem();
        $extensions = [];

        foreach ($this->files as $file) {
            if ($fs->exists($file)) {
                $this->output->writeln("<info>Analyzing file: {$file}</info>");
                $data = json_decode(file_get_contents($file), true);

                if (!$data) {
                    $this->output->writeln("<error>Error parsing {$file}</error>");
                    continue;
                }

                $extensions = array_merge($extensions, $this->extractExtensions($data));
            }
        }

        if (empty($extensions)) {
            $this->output->writeln('<comment>No extensions found.</comment>');
            return static::SUCCESS;
        }

        $extensions = array_unique($extensions);
        sort($extensions);

        $this->output->writeln("\n<info>Required PHP extensions:</info>");
        $this->output->writeln(implode(',', array_map(fn ($ext) => substr($ext, 4), $extensions)));

        return static::SUCCESS;
    }

    private function extractExtensions(array $data): array
    {
        return array_merge(
            ...array_map(
                function ($package) {
                    return isset($package['require']) ? $this->filterExtensions($package['require']) : [];
                },
                $data['packages'] ?? [$data]
            )
        );
    }

    private function filterExtensions(array $requirements): array
    {
        return array_keys(array_filter($requirements, function ($key) {
            return str_starts_with($key, 'ext-');
        }, ARRAY_FILTER_USE_KEY));
    }
}
