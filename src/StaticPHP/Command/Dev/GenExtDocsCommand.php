<?php

declare(strict_types=1);

namespace StaticPHP\Command\Dev;

use StaticPHP\Command\BaseCommand;
use StaticPHP\Config\PackageConfig;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('dev:gen-ext-docs', 'Generate extension list JSON for documentation', [], true)]
class GenExtDocsCommand extends BaseCommand
{
    protected bool $no_motd = true;

    public function handle(): int
    {
        if (!spc_mode(SPC_MODE_SOURCE)) {
            $this->output->writeln('<error>This command is only available in source mode.</error>');
            return static::USER_ERROR;
        }

        $all = PackageConfig::getAll();
        $extensions = [];

        foreach ($all as $pkg_name => $config) {
            if (($config['type'] ?? '') !== 'php-extension') {
                continue;
            }

            // Strip ext- prefix for display name
            $name = str_starts_with($pkg_name, 'ext-') ? substr($pkg_name, 4) : $pkg_name;

            // Determine OS support from php-extension.os field.
            // If the field is absent, the extension supports all three OSes.
            $os_list = $config['php-extension']['os'] ?? null;
            if ($os_list === null) {
                $linux = true;
                $macos = true;
                $windows = true;
            } else {
                $linux = in_array('Linux', $os_list, true);
                $macos = in_array('Darwin', $os_list, true);
                $windows = in_array('Windows', $os_list, true);
            }

            $extensions[] = [
                'name' => $name,
                'linux' => $linux,
                'macos' => $macos,
                'windows' => $windows,
                'url' => $this->resolveSourceUrl($config, $name),
            ];
        }

        // Sort alphabetically by name
        usort($extensions, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $output = [
            'generated_at' => date('c'),
            'extensions' => $extensions,
        ];

        $output_path = ROOT_DIR . '/docs/.vitepress/ext-data.json';
        file_put_contents($output_path, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        $this->output->writeln("<info>Generated {$output_path} with " . count($extensions) . ' extensions.</info>');
        return static::SUCCESS;
    }

    private function resolveSourceUrl(array $config, string $ext_name): ?string
    {
        $source = $config['artifact']['source'] ?? null;
        if ($source === null) {
            return null;
        }

        return match ($source['type'] ?? '') {
            'pecl' => 'https://pecl.php.net/package/' . ($source['name'] ?? $ext_name),
            'git' => rtrim($source['url'] ?? '', '/'),
            'ghtar', 'ghrel', 'ghtagtar', 'pie' => isset($source['repo'])
                ? 'https://github.com/' . $source['repo']
                : null,
            default => null,
        };
    }
}
