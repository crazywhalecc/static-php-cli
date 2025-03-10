<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\store\FileSystem;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'dump-extensions', description: 'Determines the required php extensions')]
class DumpExtensionsCommand extends BaseCommand
{
    protected bool $no_motd = true;

    public function configure(): void
    {
        // path to project files or specific composer file
        $this->addArgument('path', InputArgument::OPTIONAL, 'Path to project root', '.');
        $this->addOption('format', 'F', InputOption::VALUE_REQUIRED, 'Parsed output format', 'default');
        // output zero extension replacement rather than exit as failure
        $this->addOption('no-ext-output', 'N', InputOption::VALUE_REQUIRED, 'When no extensions found, output default combination (comma separated)');
        // no dev
        $this->addOption('no-dev', null, null, 'Do not include dev dependencies');
        // no spc filter
        $this->addOption('no-spc-filter', 'S', null, 'Do not use SPC filter to determine the required extensions');
    }

    public function handle(): int
    {
        $path = FileSystem::convertPath($this->getArgument('path'));

        $path_installed = FileSystem::convertPath(rtrim($path, '/\\') . '/vendor/composer/installed.json');
        $path_lock = FileSystem::convertPath(rtrim($path, '/\\') . '/composer.lock');

        $ext_installed = $this->extractFromInstalledJson($path_installed, !$this->getOption('no-dev'));
        if ($ext_installed === null) {
            if ($this->getOption('format') === 'default') {
                $this->output->writeln('<comment>vendor/composer/installed.json load failed, skipped</comment>');
            }
            $ext_installed = [];
        }

        $ext_lock = $this->extractFromComposerLock($path_lock, !$this->getOption('no-dev'));
        if ($ext_lock === null) {
            $this->output->writeln('<error>composer.lock load failed</error>');
            return static::FAILURE;
        }

        $extensions = array_unique(array_merge($ext_installed, $ext_lock));
        sort($extensions);

        if (empty($extensions)) {
            if ($this->getOption('no-ext-output')) {
                $this->outputExtensions(explode(',', $this->getOption('no-ext-output')));
                return static::SUCCESS;
            }
            $this->output->writeln('<error>No extensions found</error>');
            return static::FAILURE;
        }

        $this->outputExtensions($extensions);
        return static::SUCCESS;
    }

    private function filterExtensions(array $requirements): array
    {
        return array_map(
            fn ($key) => substr($key, 4),
            array_keys(
                array_filter($requirements, function ($key) {
                    return str_starts_with($key, 'ext-');
                }, ARRAY_FILTER_USE_KEY)
            )
        );
    }

    private function loadJson(string $file): array|bool
    {
        if (!file_exists($file)) {
            return false;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!$data) {
            return false;
        }
        return $data;
    }

    private function extractFromInstalledJson(string $file, bool $include_dev = true): ?array
    {
        if (!($data = $this->loadJson($file))) {
            return null;
        }

        $packages = $data['packages'] ?? [];

        if (!$include_dev) {
            $packages = array_filter($packages, fn ($package) => !in_array($package['name'], $data['dev-package-names'] ?? []));
        }

        return array_merge(
            ...array_map(fn ($x) => isset($x['require']) ? $this->filterExtensions($x['require']) : [], $packages)
        );
    }

    private function extractFromComposerLock(string $file, bool $include_dev = true): ?array
    {
        if (!($data = $this->loadJson($file))) {
            return null;
        }

        // get packages ext
        $packages = $data['packages'] ?? [];
        $exts = array_merge(
            ...array_map(fn ($package) => $this->filterExtensions($package['require'] ?? []), $packages)
        );

        // get dev packages ext
        if ($include_dev) {
            $packages = $data['packages-dev'] ?? [];
            $exts = array_merge(
                $exts,
                ...array_map(fn ($package) => $this->filterExtensions($package['require'] ?? []), $packages)
            );
        }

        // get require ext
        $platform = $data['platform'] ?? [];
        $exts = array_merge($exts, $this->filterExtensions($platform));

        // get require-dev ext
        if ($include_dev) {
            $platform = $data['platform-dev'] ?? [];
            $exts = array_merge($exts, $this->filterExtensions($platform));
        }

        return $exts;
    }

    private function outputExtensions(array $extensions): void
    {
        if (!$this->getOption('no-spc-filter')) {
            $extensions = $this->parseExtensionList($extensions);
        }
        switch ($this->getOption('format')) {
            case 'json':
                $this->output->writeln(json_encode($extensions, JSON_PRETTY_PRINT));
                break;
            case 'text':
                $this->output->writeln(implode(',', $extensions));
                break;
            default:
                $this->output->writeln('<info>Required PHP extensions' . ($this->getOption('no-dev') ? ' (without dev)' : '') . ':</info>');
                $this->output->writeln(implode(',', $extensions));
        }
    }
}
