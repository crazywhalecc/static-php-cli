<?php

declare(strict_types=1);

namespace StaticPHP\Command\Dev;

use StaticPHP\Command\BaseCommand;
use StaticPHP\Registry\Registry;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('dev:sort-config', 'Sort artifact configuration files alphabetically')]
class SortConfigCommand extends BaseCommand
{
    public function handle(): int
    {
        // get loaded configs
        $loded_configs = Registry::getLoadedArtifactConfigs();
        foreach ($loded_configs as $file) {
            $this->sortConfigFile($file);
        }
        $loaded_pkg_configs = Registry::getLoadedPackageConfigs();
        foreach ($loaded_pkg_configs as $file) {
            $this->sortConfigFile($file);
        }
        return static::SUCCESS;
    }

    private function sortConfigFile(mixed $file): void
    {
        $content = file_get_contents($file);
        if ($content === false) {
            $this->output->writeln("Failed to read artifact config file: {$file}");
            return;
        }
        $data = json_decode($content, true);
        if (!is_array($data)) {
            $this->output->writeln("Invalid JSON format in artifact config file: {$file}");
            return;
        }
        ksort($data);
        foreach ($data as $artifact_name => &$config) {
            ksort($config);
        }
        unset($config);
        $new_content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($file, $new_content);
        $this->output->writeln("Sorted artifact config file: {$file}");
    }
}
