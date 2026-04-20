<?php

declare(strict_types=1);

namespace StaticPHP\Command\Dev;

use StaticPHP\Command\BaseCommand;
use StaticPHP\Config\PackageConfig;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('dev:gen-deps-data', 'Generate package dependency data JSON for documentation', [], true)]
class GenDepsDataCommand extends BaseCommand
{
    private const PLATFORMS = ['linux', 'macos', 'windows'];

    protected bool $no_motd = true;

    public function handle(): int
    {
        if (!spc_mode(SPC_MODE_SOURCE)) {
            $this->output->writeln('<error>This command is only available in source mode.</error>');
            return static::USER_ERROR;
        }

        $all = PackageConfig::getAll();
        $packages = [];

        foreach ($all as $pkg_name => $config) {
            $type = $config['type'] ?? 'unknown';

            // Build platform-specific dep/suggest data
            $platforms = [];
            foreach (self::PLATFORMS as $platform) {
                $platforms[$platform] = [
                    'depends' => $this->resolvePlatformList($config, 'depends', $platform),
                    'suggests' => $this->resolvePlatformList($config, 'suggests', $platform),
                ];
            }

            $entry = [
                'type' => $type,
                'platforms' => $platforms,
            ];

            // For php-extension, add OS support info
            if ($type === 'php-extension') {
                $os_list = $config['php-extension']['os'] ?? null;
                if ($os_list !== null) {
                    $entry['os'] = $os_list;
                }
            }

            $packages[$pkg_name] = $entry;
        }

        // Sort by type then name for readability
        uksort($packages, function ($a, $b) use ($packages) {
            $ta = $packages[$a]['type'];
            $tb = $packages[$b]['type'];
            if ($ta !== $tb) {
                return strcmp($ta, $tb);
            }
            return strcmp($a, $b);
        });

        $output_data = [
            'generated_at' => date('c'),
            'packages' => $packages,
        ];

        $output_path = ROOT_DIR . '/docs/.vitepress/deps-data.json';
        file_put_contents($output_path, json_encode($output_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        $this->output->writeln('<info>Generated ' . $output_path . ' with ' . count($packages) . ' packages.</info>');
        return static::SUCCESS;
    }

    /**
     * Resolve the value of a platform-specific array field, applying the suffix fallback chain.
     *
     * Fallback rules (same as PackageConfig::get):
     *   linux   : @linux  → @unix  → (base)
     *   macos   : @macos  → @unix  → (base)
     *   windows : @windows         → (base)
     */
    private function resolvePlatformList(array $config, string $field, string $platform): array
    {
        return match ($platform) {
            'linux' => $config["{$field}@linux"] ?? $config["{$field}@unix"] ?? $config[$field] ?? [],
            'macos' => $config["{$field}@macos"] ?? $config["{$field}@unix"] ?? $config[$field] ?? [],
            'windows' => $config["{$field}@windows"] ?? $config[$field] ?? [],
            default => $config[$field] ?? [],
        };
    }
}
