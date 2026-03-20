<?php

declare(strict_types=1);

namespace StaticPHP\Command\Dev;

use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Command\BaseCommand;
use StaticPHP\Config\ArtifactConfig;
use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Registry\PackageLoader;
use StaticPHP\Registry\Registry;
use StaticPHP\Runtime\SystemTarget;
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
        // Resolve the actual artifact name:
        //   - string field  → named reference (e.g. php → php-src)
        //   - array field   → inline artifact, key is package name
        //   - null          → no artifact, or may match by package name
        $artifactField = $pkgConfig['artifact'] ?? null;
        $artifactName = is_string($artifactField) ? $artifactField : $packageName;
        $artifactConfig = ArtifactConfig::get($artifactName);
        $pkgInfo = Registry::getPackageConfigInfo($packageName);
        $artifactInfo = Registry::getArtifactConfigInfo($artifactName);
        $annotationInfo = PackageLoader::getPackageAnnotationInfo($packageName);
        $cacheInfo = $this->resolveCacheInfo($artifactName, $artifactConfig);

        if ($this->getOption('json')) {
            return $this->outputJson($packageName, $artifactName, $pkgConfig, $artifactConfig, $pkgInfo, $artifactInfo, $annotationInfo, $cacheInfo);
        }

        return $this->outputTerminal($packageName, $pkgConfig, $artifactConfig, $pkgInfo, $artifactInfo, $annotationInfo, $cacheInfo);
    }

    private function outputJson(string $name, string $artifactName, array $pkgConfig, ?array $artifactConfig, ?array $pkgInfo, ?array $artifactInfo, ?array $annotationInfo, ?array $cacheInfo): int
    {
        $data = [
            'name' => $name,
            'registry' => $pkgInfo['registry'] ?? null,
            'package_config_file' => $pkgInfo ? $this->toRelativePath($pkgInfo['config']) : null,
            'package' => $pkgConfig,
        ];

        if ($artifactConfig !== null) {
            $data['artifact_name'] = $artifactName !== $name ? $artifactName : null;
            $data['artifact_config_file'] = $artifactInfo ? $this->toRelativePath($artifactInfo['config']) : null;
            $data['artifact'] = $this->splitArtifactConfig($artifactConfig);
        }

        if ($annotationInfo !== null) {
            $data['annotations'] = $annotationInfo;
        }

        if ($cacheInfo !== null) {
            $data['cache'] = $cacheInfo;
        }

        $this->output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return static::SUCCESS;
    }

    private function outputTerminal(string $name, array $pkgConfig, ?array $artifactConfig, ?array $pkgInfo, ?array $artifactInfo, ?array $annotationInfo, ?array $cacheInfo): int
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
            $artifactField = $pkgConfig['artifact'] ?? null;
            if (is_string($artifactField)) {
                // Named reference: show the artifact name it points to
                $this->output->writeln("<comment>── Artifact Config ──</comment>  <info>artifact:</info> <fg=cyan>{$artifactField}</>  <info>file:</info> {$artifactFile}");
            } elseif (is_array($artifactField)) {
                $this->output->writeln("<comment>── Artifact Config ──</comment>  <info>file:</info> {$artifactFile}");
                $this->output->writeln('<info>  (inline in package config)</info>');
            } else {
                $this->output->writeln("<comment>── Artifact Config ──</comment>  <info>file:</info> {$artifactFile}");
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

        // Annotation section
        $this->outputAnnotationSection($name, $annotationInfo);

        // Cache status section
        $this->outputCacheSection($cacheInfo);

        return static::SUCCESS;
    }

    private function outputAnnotationSection(string $packageName, ?array $annotationInfo): void
    {
        if ($annotationInfo === null) {
            $this->output->writeln('<comment>── Annotations ──</comment>  <fg=gray>(no annotation class registered)</>');
            $this->output->writeln('');
            return;
        }

        $shortClass = $this->classBaseName($annotationInfo['class']);
        $this->output->writeln("<comment>── Annotations ──</comment>  <info>class:</info> <fg=cyan>{$shortClass}</>");
        $this->output->writeln("  <fg=gray>{$annotationInfo['class']}</>");

        // Method-level hooks
        $methods = $annotationInfo['methods'];
        if (!empty($methods)) {
            $this->output->writeln('');
            $this->output->writeln('  <info>Method hooks:</info>');
            foreach ($methods as $methodName => $attrs) {
                $attrList = implode('  ', array_map(fn ($a) => $this->formatAttr($a), $attrs));
                $this->output->writeln("    <fg=cyan>{$methodName}()</>  {$attrList}");
            }
        }

        // Before-stage hooks targeting this package (inbound)
        $beforeStages = $annotationInfo['before_stages'];
        if (!empty($beforeStages)) {
            $this->output->writeln('');
            $this->output->writeln('  <info>Before-stage hooks (inbound):</info>');
            foreach ($beforeStages as $stage => $hooks) {
                foreach ($hooks as $hook) {
                    $source = $this->classBaseName($hook['class']) . '::' . $hook['method'] . '()';
                    $cond = $hook['only_when'] !== null ? "  <fg=gray>(only_when: {$hook['only_when']})</>" : '';
                    $this->output->writeln("    <fg=yellow>{$stage}</>  ← {$source}{$cond}");
                }
            }
        }

        // After-stage hooks targeting this package (inbound)
        $afterStages = $annotationInfo['after_stages'];
        if (!empty($afterStages)) {
            $this->output->writeln('');
            $this->output->writeln('  <info>After-stage hooks (inbound):</info>');
            foreach ($afterStages as $stage => $hooks) {
                foreach ($hooks as $hook) {
                    $source = $this->classBaseName($hook['class']) . '::' . $hook['method'] . '()';
                    $cond = $hook['only_when'] !== null ? "  <fg=gray>(only_when: {$hook['only_when']})</>" : '';
                    $this->output->writeln("    <fg=yellow>{$stage}</>  ← {$source}{$cond}");
                }
            }
        }

        // Outbound hooks: stages this package's class registers on other packages (exclude self-hooks)
        $outboundBefore = $annotationInfo['outbound_before_stages'] ?? [];
        $outboundAfter = $annotationInfo['outbound_after_stages'] ?? [];
        // Filter out entries targeting the same package — those are already shown inbound
        $outboundBefore = array_filter($outboundBefore, fn ($pkg) => $pkg !== $packageName, ARRAY_FILTER_USE_KEY);
        $outboundAfter = array_filter($outboundAfter, fn ($pkg) => $pkg !== $packageName, ARRAY_FILTER_USE_KEY);
        if (!empty($outboundBefore) || !empty($outboundAfter)) {
            $this->output->writeln('');
            $this->output->writeln('  <info>Hooks on other packages (outbound):</info>');
            foreach ($outboundBefore as $targetPkg => $stages) {
                foreach ($stages as $stage => $hooks) {
                    foreach ($hooks as $hook) {
                        $cond = $hook['only_when'] !== null ? "  <fg=gray>(only_when: {$hook['only_when']})</>" : '';
                        $this->output->writeln("    <fg=magenta>#[BeforeStage]</>  → <fg=cyan>{$targetPkg}</> <fg=yellow>{$stage}</>  {$hook['method']}(){$cond}");
                    }
                }
            }
            foreach ($outboundAfter as $targetPkg => $stages) {
                foreach ($stages as $stage => $hooks) {
                    foreach ($hooks as $hook) {
                        $cond = $hook['only_when'] !== null ? "  <fg=gray>(only_when: {$hook['only_when']})</>" : '';
                        $this->output->writeln("    <fg=magenta>#[AfterStage]</>   → <fg=cyan>{$targetPkg}</> <fg=yellow>{$stage}</>  {$hook['method']}(){$cond}");
                    }
                }
            }
        }

        $this->output->writeln('');
    }

    /**
     * Format a single attribute entry (from annotation_map) as a colored inline string.
     *
     * @param array{attr: string, args: array<string, mixed>} $attr
     */
    private function formatAttr(array $attr): string
    {
        $name = $attr['attr'];
        $args = $attr['args'];
        if (empty($args)) {
            return "<fg=magenta>#[{$name}]</>";
        }
        $argStr = implode(', ', array_map(
            fn ($v) => is_string($v) ? "'{$v}'" : (string) $v,
            array_values($args)
        ));
        return "<fg=magenta>#[{$name}({$argStr})]</>";
    }

    /** Return the trailing class name component without the namespace. */
    private function classBaseName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
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

    /**
     * Build cache status data for display/JSON.
     * Returns null when there is no artifact config for this package.
     */
    private function resolveCacheInfo(string $name, ?array $artifactConfig): ?array
    {
        if ($artifactConfig === null) {
            return null;
        }
        $cache = ApplicationContext::get(ArtifactCache::class);
        $currentPlatform = SystemTarget::getCurrentPlatformString();
        $hasSource = array_key_exists('source', $artifactConfig) || array_key_exists('source-mirror', $artifactConfig);
        $hasBinary = array_key_exists('binary', $artifactConfig) || array_key_exists('binary-mirror', $artifactConfig);
        return [
            'current_platform' => $currentPlatform,
            'has_source' => $hasSource,
            'has_binary' => $hasBinary,
            'source' => $hasSource ? [
                'downloaded' => $cache->isSourceDownloaded($name),
                'info' => $cache->getSourceInfo($name),
            ] : null,
            'binary' => $hasBinary ? $cache->getAllBinaryInfo($name) : null,
        ];
    }

    private function outputCacheSection(?array $cacheInfo): void
    {
        if ($cacheInfo === null) {
            $this->output->writeln('<comment>── Cache Status ──</comment>  <fg=gray>(no artifact config)</>');
            $this->output->writeln('');
            return;
        }

        $platform = $cacheInfo['current_platform'];
        $this->output->writeln("<comment>── Cache Status ──</comment>  <fg=gray>current platform: {$platform}</>");

        // Source
        $this->output->writeln('');
        $this->output->writeln('  <info>source:</info>');
        if (!$cacheInfo['has_source']) {
            $this->output->writeln('    <fg=gray>─ not applicable</>');
        } elseif ($cacheInfo['source']['downloaded'] && $cacheInfo['source']['info'] !== null) {
            $this->output->writeln('    <fg=green>✓ downloaded</>  ' . $this->formatCacheEntry($cacheInfo['source']['info']));
        } else {
            $this->output->writeln('    <fg=yellow>✗ not downloaded</>');
        }

        // Binary
        $this->output->writeln('');
        $this->output->writeln('  <info>binary:</info>');
        if (!$cacheInfo['has_binary']) {
            $this->output->writeln('    <fg=gray>─ not applicable</>');
        } elseif (empty($cacheInfo['binary'])) {
            $this->output->writeln("    <fg=yellow>✗ {$platform}</>  <fg=gray>(current — not cached)</>");
        } else {
            $allBinary = $cacheInfo['binary'];
            foreach ($allBinary as $binPlatform => $binInfo) {
                $isCurrent = $binPlatform === $platform;
                $tag = $isCurrent ? ' <fg=gray>(current)</>' : '';
                if ($binInfo !== null) {
                    $this->output->writeln("    <fg=green>✓ {$binPlatform}</>{$tag}  " . $this->formatCacheEntry($binInfo));
                } else {
                    $this->output->writeln("    <fg=red>✗ {$binPlatform}</>{$tag}");
                }
            }
            // Show current platform if not already listed
            if (!array_key_exists($platform, $allBinary)) {
                $this->output->writeln("    <fg=yellow>✗ {$platform}</>  <fg=gray>(current — not cached)</>");
            }
        }

        $this->output->writeln('');
    }

    private function formatCacheEntry(array $info): string
    {
        $type = $info['cache_type'] ?? '?';
        $version = $info['version'] !== null ? "  {$info['version']}" : '';
        $time = isset($info['time']) ? '  ' . date('Y-m-d H:i', (int) $info['time']) : '';
        $file = match ($type) {
            'archive', 'file' => isset($info['filename']) ? "  <fg=gray>{$info['filename']}</>" : '',
            'git', 'local' => isset($info['dirname']) ? "  <fg=gray>{$info['dirname']}</>" : '',
            default => '',
        };
        return "<fg=cyan>[{$type}]</>{$version}{$time}{$file}";
    }
}
