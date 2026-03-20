<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use StaticPHP\Registry\PackageLoader;
use StaticPHP\Util\DependencyResolver;
use StaticPHP\Util\InteractiveTerm;
use StaticPHP\Util\LicenseDumper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('dump-license', 'Dump licenses for artifacts')]
class DumpLicenseCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addArgument('artifacts', InputArgument::OPTIONAL, 'Specific artifacts to dump licenses, comma separated, e.g "php-src,openssl,curl"');

        // v2 compatible options
        $this->addOption('for-extensions', 'e', InputOption::VALUE_REQUIRED, 'Dump by extensions (automatically includes php-src), e.g "openssl,mbstring"');
        $this->addOption('for-libs', 'l', InputOption::VALUE_REQUIRED, 'Dump by libraries, e.g "openssl,zlib,curl"');

        // v3 options
        $this->addOption('for-packages', 'p', InputOption::VALUE_REQUIRED, 'Dump by packages, e.g "php,libssl,libcurl"');
        $this->addOption('dump-dir', 'd', InputOption::VALUE_REQUIRED, 'Target directory for dumped licenses', BUILD_ROOT_PATH . '/license');
        $this->addOption('without-suggests', null, null, 'Do not include suggested packages when using --for-extensions or --for-packages');
    }

    public function handle(): int
    {
        $dumper = new LicenseDumper();
        $dump_dir = $this->getOption('dump-dir');
        $artifacts_to_dump = [];

        // Handle direct artifact argument
        if ($artifacts = $this->getArgument('artifacts')) {
            $artifacts_to_dump = array_merge($artifacts_to_dump, parse_comma_list($artifacts));
        }

        // Handle --for-extensions option
        if ($exts = $this->getOption('for-extensions')) {
            $artifacts_to_dump = array_merge(
                $artifacts_to_dump,
                $this->resolveFromExtensions(parse_extension_list($exts))
            );
        }

        // Handle --for-libs option (v2 compat)
        if ($libs = $this->getOption('for-libs')) {
            $artifacts_to_dump = array_merge(
                $artifacts_to_dump,
                $this->resolveFromPackages(parse_comma_list($libs))
            );
        }

        // Handle --for-packages option
        if ($packages = $this->getOption('for-packages')) {
            $artifacts_to_dump = array_merge(
                $artifacts_to_dump,
                $this->resolveFromPackages(parse_comma_list($packages))
            );
        }

        // Check if any artifacts to dump
        if (empty($artifacts_to_dump)) {
            $this->output->writeln('<error>No artifacts specified. Use one of:</error>');
            $this->output->writeln('  - Direct argument: <info>dump-license php-src,openssl,curl</info>');
            $this->output->writeln('  - --for-extensions: <info>dump-license --for-extensions=openssl,mbstring</info>');
            $this->output->writeln('  - --for-libs: <info>dump-license --for-libs=openssl,zlib</info>');
            $this->output->writeln('  - --for-packages: <info>dump-license --for-packages=php,libssl</info>');
            return static::USER_ERROR;
        }

        // Deduplicate artifacts
        $artifacts_to_dump = array_values(array_unique($artifacts_to_dump));

        logger()->info('Dumping licenses for ' . count($artifacts_to_dump) . ' artifact(s)');
        logger()->debug('Artifacts: ' . implode(', ', $artifacts_to_dump));

        // Add artifacts to dumper
        $dumper->addArtifacts($artifacts_to_dump);

        // Dump
        $success = $dumper->dump($dump_dir);

        if ($success) {
            InteractiveTerm::success('Licenses dumped successfully: ' . $dump_dir);
            // $this->output->writeln("<info>✓ Successfully dumped licenses to: {$dump_dir}</info>");
            // $this->output->writeln("<comment>  Total artifacts: " . count($artifacts_to_dump) . '</comment>');
            return static::SUCCESS;
        }

        $this->output->writeln('<error>Failed to dump licenses</error>');
        return static::INTERNAL_ERROR;
    }

    /**
     * Resolve artifacts from extension names.
     *
     * @param  array<string> $extensions Extension names
     * @return array<string> Artifact names
     */
    private function resolveFromExtensions(array $extensions): array
    {
        // Convert extension names to package names
        $packages = array_map(fn ($ext) => "ext-{$ext}", $extensions);

        // Automatically include php-related artifacts
        array_unshift($packages, 'php');
        array_unshift($packages, 'php-micro');
        array_unshift($packages, 'php-embed');
        array_unshift($packages, 'php-fpm');

        return $this->resolveFromPackages($packages);
    }

    /**
     * Resolve artifacts from package names.
     *
     * @param  array<string> $packages Package names
     * @return array<string> Artifact names
     */
    private function resolveFromPackages(array $packages): array
    {
        $artifacts = [];
        $include_suggests = !$this->getOption('without-suggests');

        // Resolve package dependencies
        $resolved_packages = DependencyResolver::resolve($packages, [], $include_suggests);

        foreach ($resolved_packages as $pkg_name) {
            try {
                $pkg = PackageLoader::getPackage($pkg_name);
                if ($artifact = $pkg->getArtifact()) {
                    $artifacts[] = $artifact->getName();
                }
            } catch (\Throwable $e) {
                logger()->debug("Package {$pkg_name} has no artifact or failed to load: {$e->getMessage()}");
            }
        }

        return array_unique($artifacts);
    }
}
