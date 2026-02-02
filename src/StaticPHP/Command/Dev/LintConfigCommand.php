<?php

declare(strict_types=1);

namespace StaticPHP\Command\Dev;

use StaticPHP\Command\BaseCommand;
use StaticPHP\Registry\Registry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Yaml\Yaml;

#[AsCommand('dev:lint-config', 'Lint configuration file format', ['dev:sort-config'])]
class LintConfigCommand extends BaseCommand
{
    public function handle(): int
    {
        // get loaded configs
        $loded_configs = Registry::getLoadedArtifactConfigs();
        foreach ($loded_configs as $file) {
            $this->sortConfigFile($file, 'artifact');
        }
        $loaded_pkg_configs = Registry::getLoadedPackageConfigs();
        foreach ($loaded_pkg_configs as $file) {
            $this->sortConfigFile($file, 'package');
        }
        return static::SUCCESS;
    }

    public function artifactSortKey(string $a, string $b): int
    {
        // sort by predefined order, other not matching keys go to the end alphabetically
        $order = ['source', 'source-mirror', 'binary', 'binary-mirror', 'metadata'];

        $pos_a = array_search($a, $order, true);
        $pos_b = array_search($b, $order, true);

        // Both in order list
        if ($pos_a !== false && $pos_b !== false) {
            return $pos_a <=> $pos_b;
        }

        // Only $a in order list
        if ($pos_a !== false) {
            return -1;
        }

        // Only $b in order list
        if ($pos_b !== false) {
            return 1;
        }

        // Neither in order list, sort alphabetically
        return $a <=> $b;
    }

    public function packageSortKey(string $a, string $b): int
    {
        // sort by predefined order, other not matching keys go to the end alphabetically
        $order = ['type', 'artifact'];

        // Handle suffix patterns (e.g., 'depends@unix', 'static-libs@windows')
        $base_a = preg_replace('/@(unix|windows|macos|linux|freebsd|bsd)$/', '', $a);
        $base_b = preg_replace('/@(unix|windows|macos|linux|freebsd|bsd)$/', '', $b);

        $pos_a = array_search($base_a, $order, true);
        $pos_b = array_search($base_b, $order, true);

        // Both in order list
        if ($pos_a !== false && $pos_b !== false) {
            if ($pos_a === $pos_b) {
                // Same base field, sort by suffix
                return $a <=> $b;
            }
            return $pos_a <=> $pos_b;
        }

        // Only $a in order list
        if ($pos_a !== false) {
            return -1;
        }

        // Only $b in order list
        if ($pos_b !== false) {
            return 1;
        }

        // Neither in order list, sort alphabetically
        return $a <=> $b;
    }

    private function sortConfigFile(mixed $file, string $config_type): void
    {
        // read file content with different extensions
        $content = file_get_contents($file);
        if ($content === false) {
            $this->output->writeln("Failed to read artifact config file: {$file}");
            return;
        }
        $data = match (pathinfo($file, PATHINFO_EXTENSION)) {
            'json' => json_decode($content, true),
            'yml', 'yaml' => Yaml::parse($content), // skip yaml files for now
            default => null,
        };
        if (!is_array($data)) {
            $this->output->writeln("Invalid JSON format in artifact config file: {$file}");
            return;
        }
        ksort($data);
        foreach ($data as $artifact_name => &$config) {
            uksort($config, $config_type === 'artifact' ? [$this, 'artifactSortKey'] : [$this, 'packageSortKey']);
        }
        unset($config);
        $new_content = match (pathinfo($file, PATHINFO_EXTENSION)) {
            'json' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            'yml', 'yaml' => Yaml::dump($data, 4, 2),
            default => null,
        };
        file_put_contents($file, $new_content);
        $this->output->writeln("Sorted artifact config file: {$file}");
    }
}
