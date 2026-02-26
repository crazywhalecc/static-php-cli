<?php

declare(strict_types=1);

namespace StaticPHP\Command\Dev;

use StaticPHP\Command\BaseCommand;
use StaticPHP\Registry\PackageLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('dev:dump-stages', 'Dump all package stages with their file locations for quick indexing')]
class DumpStagesCommand extends BaseCommand
{
    protected bool $no_motd = true;

    public function configure(): void
    {
        $this->addArgument('packages', InputArgument::OPTIONAL, 'Comma-separated list of packages to dump, e.g. "openssl,zlib,curl". Dumps all packages if omitted.');
        $this->addArgument('output', InputArgument::OPTIONAL, 'Output file path', ROOT_DIR . '/dump-stages.json');
        $this->addOption('relative', 'r', InputOption::VALUE_NONE, 'Output file paths relative to ROOT_DIR');
    }

    public function handle(): int
    {
        $outputFile = $this->getArgument('output');
        $useRelative = (bool) $this->getOption('relative');

        $filterPackages = null;
        if ($packagesArg = $this->getArgument('packages')) {
            $filterPackages = array_flip(parse_comma_list($packagesArg));
        }

        $result = [];

        foreach (PackageLoader::getPackages() as $name => $pkg) {
            if ($filterPackages !== null && !isset($filterPackages[$name])) {
                continue;
            }
            $entry = [
                'type' => $pkg->getType(),
                'stages' => [],
                'before_stages' => [],
                'after_stages' => [],
            ];

            // Resolve main stages
            foreach ($pkg->getStages() as $stageName => $callable) {
                $location = $this->resolveCallableLocation($callable);
                if ($location !== null && $useRelative) {
                    $location['file'] = $this->toRelativePath($location['file']);
                }
                $entry['stages'][$stageName] = $location;
            }

            $result[$name] = $entry;
        }

        // Resolve before/after stage external callbacks
        foreach (PackageLoader::getAllBeforeStages() as $pkgName => $stages) {
            if ($filterPackages !== null && !isset($filterPackages[$pkgName])) {
                continue;
            }
            foreach ($stages as $stageName => $callbacks) {
                foreach ($callbacks as [$callable, $onlyWhen]) {
                    $location = $this->resolveCallableLocation($callable);
                    if ($location !== null && $useRelative) {
                        $location['file'] = $this->toRelativePath($location['file']);
                    }
                    $entry_data = $location ?? [];
                    if ($onlyWhen !== null) {
                        $entry_data['only_when_package_resolved'] = $onlyWhen;
                    }
                    $result[$pkgName]['before_stages'][$stageName][] = $entry_data;
                }
            }
        }

        foreach (PackageLoader::getAllAfterStages() as $pkgName => $stages) {
            if ($filterPackages !== null && !isset($filterPackages[$pkgName])) {
                continue;
            }
            foreach ($stages as $stageName => $callbacks) {
                foreach ($callbacks as [$callable, $onlyWhen]) {
                    $location = $this->resolveCallableLocation($callable);
                    if ($location !== null && $useRelative) {
                        $location['file'] = $this->toRelativePath($location['file']);
                    }
                    $entry_data = $location ?? [];
                    if ($onlyWhen !== null) {
                        $entry_data['only_when_package_resolved'] = $onlyWhen;
                    }
                    $result[$pkgName]['after_stages'][$stageName][] = $entry_data;
                }
            }
        }

        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($outputFile, $json . PHP_EOL);

        $this->output->writeln('<info>Dumped stages for ' . count($result) . " package(s) to: {$outputFile}</info>");
        return static::SUCCESS;
    }

    /**
     * Resolve the file, start line, class and method name of a callable using reflection.
     *
     * @return null|array{file: string, line: false|int, class: string, method: string}
     */
    private function resolveCallableLocation(mixed $callable): ?array
    {
        try {
            if (is_array($callable) && count($callable) === 2) {
                $ref = new \ReflectionMethod($callable[0], $callable[1]);
                return [
                    'class' => $ref->getDeclaringClass()->getName(),
                    'method' => $ref->getName(),
                    'file' => (string) $ref->getFileName(),
                    'line' => $ref->getStartLine(),
                ];
            }
            if ($callable instanceof \Closure) {
                $ref = new \ReflectionFunction($callable);
                $scopeClass = $ref->getClosureScopeClass();
                return [
                    'class' => $scopeClass !== null ? $scopeClass->getName() : '{closure}',
                    'method' => '{closure}',
                    'file' => (string) $ref->getFileName(),
                    'line' => $ref->getStartLine(),
                ];
            }
            if (is_string($callable) && str_contains($callable, '::')) {
                [$class, $method] = explode('::', $callable, 2);
                $ref = new \ReflectionMethod($class, $method);
                return [
                    'class' => $ref->getDeclaringClass()->getName(),
                    'method' => $ref->getName(),
                    'file' => (string) $ref->getFileName(),
                    'line' => $ref->getStartLine(),
                ];
            }
        } catch (\ReflectionException) {
            // ignore
        }
        return null;
    }

    private function toRelativePath(string $absolutePath): string
    {
        $root = rtrim(ROOT_DIR, '/') . '/';
        if (str_starts_with($absolutePath, $root)) {
            return substr($absolutePath, strlen($root));
        }
        return $absolutePath;
    }
}
