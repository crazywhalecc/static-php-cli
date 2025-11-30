<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Artifact\ArtifactExtractor;
use StaticPHP\Artifact\ArtifactLoader;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Package\PackageLoader;
use StaticPHP\Util\DependencyResolver;
use StaticPHP\Util\InteractiveTerm;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use ZM\Logger\ConsoleColor;

#[AsCommand('extract')]
class ExtractCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->setDescription('Extract downloaded artifacts to their target locations');

        $this->addArgument('artifacts', InputArgument::OPTIONAL, 'Specific artifacts to extract, comma separated, e.g "php-src,openssl,curl"');

        $this->addOption('for-extensions', 'e', InputOption::VALUE_REQUIRED, 'Extract artifacts for extensions, e.g "openssl,mbstring"');
        $this->addOption('for-libs', 'l', InputOption::VALUE_REQUIRED, 'Extract artifacts for libraries, e.g "libcares,openssl"');
        $this->addOption('for-packages', null, InputOption::VALUE_REQUIRED, 'Extract artifacts for packages, e.g "php,libssl,libcurl"');
        $this->addOption('without-suggests', null, null, 'Do not include suggested packages when using --for-extensions');
        $this->addOption('force-source', null, null, 'Force extract source even if binary is available');
    }

    public function handle(): int
    {
        $cache = ApplicationContext::get(ArtifactCache::class);
        $extractor = new ArtifactExtractor($cache);
        $force_source = (bool) $this->getOption('force-source');

        $artifacts = [];

        // Direct artifact names
        if ($artifact_arg = $this->getArgument('artifacts')) {
            $artifact_names = parse_comma_list($artifact_arg);
            foreach ($artifact_names as $name) {
                $artifact = ArtifactLoader::getArtifactInstance($name);
                if ($artifact === null) {
                    $this->output->writeln("<error>Artifact '{$name}' not found.</error>");
                    return static::FAILURE;
                }
                $artifacts[$name] = $artifact;
            }
        }

        // Resolve packages and get their artifacts
        $packages = [];
        if ($exts = $this->getOption('for-extensions')) {
            $packages = array_map(fn ($x) => "ext-{$x}", parse_extension_list($exts));
            // Include php package when using for-extensions
            array_unshift($packages, 'php');
        }
        if ($libs = $this->getOption('for-libs')) {
            $packages = array_merge($packages, parse_comma_list($libs));
        }
        if ($pkgs = $this->getOption('for-packages')) {
            $packages = array_merge($packages, parse_comma_list($pkgs));
        }

        if (!empty($packages)) {
            $resolved = DependencyResolver::resolve($packages, [], !$this->getOption('without-suggests'));
            foreach ($resolved as $pkg_name) {
                $pkg = PackageLoader::getPackage($pkg_name);
                if ($artifact = $pkg->getArtifact()) {
                    $artifacts[$artifact->getName()] = $artifact;
                }
            }
        }

        if (empty($artifacts)) {
            $this->output->writeln('<comment>No artifacts specified. Use artifact names or --for-extensions/--for-libs/--for-packages options.</comment>');
            $this->output->writeln('');
            $this->output->writeln('Examples:');
            $this->output->writeln('  spc extract php-src,openssl');
            $this->output->writeln('  spc extract --for-extensions=openssl,mbstring');
            $this->output->writeln('  spc extract --for-libs=libcurl,libssl');
            return static::SUCCESS;
        }

        // make php-src always extracted first
        uksort($artifacts, fn ($a, $b) => $a === 'php-src' ? -1 : ($b === 'php-src' ? 1 : 0));

        try {
            InteractiveTerm::notice('Extracting ' . count($artifacts) . ' artifacts: ' . implode(',', array_map(fn ($x) => ConsoleColor::yellow($x->getName()), $artifacts)) . '...');
            InteractiveTerm::indicateProgress('Extracting artifacts');
            foreach ($artifacts as $artifact) {
                InteractiveTerm::setMessage('Extracting artifact: ' . ConsoleColor::green($artifact->getName()));
                $extractor->extract($artifact, $force_source);
            }
            InteractiveTerm::finish('Extracted all artifacts successfully.');
        } catch (\Exception $e) {
            InteractiveTerm::finish('Extraction failed!', false);
            throw $e;
        }

        return static::SUCCESS;
    }
}
