<?php

declare(strict_types=1);

namespace StaticPHP\Command\Dev;

use StaticPHP\Command\BaseCommand;
use StaticPHP\Config\ArtifactConfig;
use StaticPHP\Config\PackageConfig;
use StaticPHP\Registry\Registry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

#[AsCommand('dev:info', 'Display configuration information for a package')]
class PackageInfoCommand extends BaseCommand
{
    protected bool $no_motd = true;

    public function configure(): void
    {
        $this->addArgument('package', InputArgument::REQUIRED, 'Package name to inspect');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON instead of colored terminal display');
    }

    public function handle(): int
    {
        $packageName = $this->getArgument('package');

        if (!PackageConfig::isPackageExists($packageName)) {
            $this->output->writeln("<error>Package '{$packageName}' not found.</error>");
            return static::USER_ERROR;
        }

        $pkgConfig = PackageConfig::get($packageName);
        $artifactConfig = ArtifactConfig::get($packageName);
        $pkgInfo = Registry::getPackageConfigInfo($packageName);
        $artifactInfo = Registry::getArtifactConfigInfo($packageName);

        if ($this->getOption('json')) {
            return $this->outputJson($packageName, $pkgConfig, $artifactConfig, $pkgInfo, $artifactInfo);
        }

        return $this->outputTerminal($packageName, $pkgConfig, $artifactConfig, $pkgInfo, $artifactInfo);
    }

    private function outputJson(string $name, array $pkgConfig, ?array $artifactConfig, ?array $pkgInfo, ?array $artifactInfo): int
    {
        $data = [
            'name' => $name,
            'registry' => $pkgInfo['registry'] ?? null,
            'package_config_file' => $pkgInfo ? $this->toRelativePath($pkgInfo['config']) : null,
            'package' => $pkgConfig,
        ];

        if ($artifactConfig !== null) {
            $data['artifact_config_file'] = $artifactInfo ? $this->toRelativePath($artifactInfo['config']) : null;
            $data['artifact'] = $this->splitArtifactConfig($artifactConfig);
        }

        $this->output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return static::SUCCESS;
    }

    private function outputTerminal(string $name, array $pkgConfig, ?array $artifactConfig, ?array $pkgInfo, ?array $artifactInfo): int
    {
        $type = $pkgConfig['type'] ?? 'unknown';
        $registry = $pkgInfo['registry'] ?? 'unknown';
        $pkgFile = $pkgInfo ? $this->toRelativePath($pkgInfo['config']) : 'unknown';

        // Header
        $this->output->writeln('');
        $this->output->writeln("<info>Package:</info> <comment>{$name}</comment>  <info>Type:</info> <comment>{$type}</comment>  <info>Registry:</info> <comment>{$registry}</comment>");
        $this->output->writeln("<info>Config file:</info> {$pkgFile}");
        $this->output->writeln('');

        // Package config fields (excluding type and artifact which are shown separately)
        $pkgFields = array_diff_key($pkgConfig, array_flip(['type', 'artifact']));
        if (!empty($pkgFields)) {
            $this->output->writeln('<comment>── Package Config ──</comment>');
            $this->printYamlBlock($pkgFields, 0);
            $this->output->writeln('');
        }

        // Artifact config
        if ($artifactConfig !== null) {
            $artifactFile = $artifactInfo ? $this->toRelativePath($artifactInfo['config']) : 'unknown';
            $this->output->writeln("<comment>── Artifact Config ──</comment>  <info>file:</info> {$artifactFile}");

            // Check if artifact config is inline (embedded in pkg config) or separate
            $inlineArtifact = $pkgConfig['artifact'] ?? null;
            if (is_array($inlineArtifact)) {
                $this->output->writeln('<info>  (inline in package config)</info>');
            }

            $split = $this->splitArtifactConfig($artifactConfig);

            foreach ($split as $section => $value) {
                $this->output->writeln('');
                $this->output->writeln("  <info>[{$section}]</info>");
                $this->printYamlBlock($value, 4);
            }
            $this->output->writeln('');
        } else {
            $this->output->writeln('<comment>── Artifact Config ──</comment>  <fg=gray>(none)</>');
            $this->output->writeln('');
        }

        return static::SUCCESS;
    }

    /**
     * Split artifact config into logical sections for cleaner display.
     *
     * @return array<string, mixed>
     */
    private function splitArtifactConfig(array $config): array
    {
        $sections = [];
        $sectionOrder = ['source', 'source-mirror', 'binary', 'binary-mirror', 'metadata'];
        foreach ($sectionOrder as $key) {
            if (array_key_exists($key, $config)) {
                $sections[$key] = $config[$key];
            }
        }
        // Any remaining unknown keys
        foreach ($config as $k => $v) {
            if (!array_key_exists($k, $sections)) {
                $sections[$k] = $v;
            }
        }
        return $sections;
    }

    /**
     * Print a value as indented YAML-style output with Symfony Console color tags.
     */
    private function printYamlBlock(mixed $value, int $indent): void
    {
        $pad = str_repeat(' ', $indent);
        if (!is_array($value)) {
            $this->output->writeln($pad . $this->colorScalar($value));
            return;
        }
        $isList = array_is_list($value);
        foreach ($value as $k => $v) {
            if ($isList) {
                if (is_array($v)) {
                    $this->output->writeln($pad . '- ');
                    $this->printYamlBlock($v, $indent + 2);
                } else {
                    $this->output->writeln($pad . '- ' . $this->colorScalar($v));
                }
            } else {
                if (is_array($v)) {
                    $this->output->writeln($pad . "<fg=cyan>{$k}</>:");
                    $this->printYamlBlock($v, $indent + 2);
                } else {
                    $this->output->writeln($pad . "<fg=cyan>{$k}</>: " . $this->colorScalar($v));
                }
            }
        }
    }

    private function colorScalar(mixed $v): string
    {
        if (is_bool($v)) {
            return '<fg=yellow>' . ($v ? 'true' : 'false') . '</>';
        }
        if (is_int($v) || is_float($v)) {
            return '<fg=yellow>' . $v . '</>';
        }
        if ($v === null) {
            return '<fg=gray>null</>';
        }
        // Strings that look like URLs
        if (is_string($v) && (str_starts_with($v, 'http://') || str_starts_with($v, 'https://'))) {
            return '<fg=blue>' . $v . '</>';
        }
        return '<fg=green>' . $v . '</>';
    }

    private function toRelativePath(string $absolutePath): string
    {
        $normalized = realpath($absolutePath) ?: $absolutePath;
        $root = rtrim(ROOT_DIR, '/') . '/';
        if (str_starts_with($normalized, $root)) {
            return substr($normalized, strlen($root));
        }
        return $normalized;
    }
}
