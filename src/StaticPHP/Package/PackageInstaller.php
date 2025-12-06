<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\Artifact\Artifact;
use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\ArtifactExtractor;
use StaticPHP\Artifact\DownloaderOptions;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Util\DependencyResolver;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use StaticPHP\Util\V2CompatLayer;
use ZM\Logger\ConsoleColor;

/**
 * PackageInstaller is responsible for installing packages within the StaticPHP framework.
 */
class PackageInstaller
{
    /** @var array<string, Package> Resolved package list */
    protected array $packages = [];

    /** @var array<string, Package> Packages to be built from source */
    protected array $build_packages = [];

    /** @var array<string, Package> Packages to be installed */
    protected array $install_packages = [];

    /** @var array<string, array<string>> Unresolved target additional dependencies defined in #[ResolveBuild] */
    protected array $target_additional_dependencies = [];

    /** @var bool Whether to download missing sources automatically */
    protected bool $download = true;

    public function __construct(protected array $options = [])
    {
        ApplicationContext::set(PackageInstaller::class, $this);
        $builder = new PackageBuilder($options);
        ApplicationContext::set(PackageBuilder::class, $builder);
        ApplicationContext::set('patch_point', '');

        // Check for no-download option
        if (!empty($options['no-download'])) {
            $this->download = false;
        }
    }

    /**
     * Add a package to the build list.
     * This means the package will be built from source.
     */
    public function addBuildPackage(LibraryPackage|string|TargetPackage $package): static
    {
        if (is_string($package)) {
            $package = PackageLoader::getPackage($package);
        }
        // special check for php target packages
        if (in_array($package->getName(), ['php', 'php-cli', 'php-fpm', 'php-micro', 'php-cgi', 'php-embed', 'frankenphp'], true)) {
            $this->handlePhpTargetPackage($package);
            return $this;
        }
        if (!$package->hasStage('build')) {
            throw new WrongUsageException("Target package '{$package->getName()}' does not define build process for current OS: " . PHP_OS_FAMILY . '.');
        }
        $this->build_packages[$package->getName()] = $package;
        return $this;
    }

    /**
     * @param  string       $name Package name
     * @return null|Package The build package instance or null if not found
     */
    public function getBuildPackage(string $name): ?Package
    {
        return $this->build_packages[$name] ?? null;
    }

    /**
     * Add a package to the installation list.
     * This means the package will try to install binary artifacts first.
     * If no artifacts found, it will fallback to build from source.
     */
    public function addInstallPackage(LibraryPackage|string $package): static
    {
        if (is_string($package)) {
            $package = PackageLoader::getPackage($package);
        }
        $this->install_packages[$package->getName()] = $package;
        return $this;
    }

    /**
     * Set whether to download packages before installation.
     */
    public function setDownload(bool $download = true): static
    {
        $this->download = $download;
        return $this;
    }

    /**
     * Run the package installation process.
     */
    public function run(bool $interactive = true, bool $disable_delay_msg = false): void
    {
        // resolve input, make dependency graph
        $this->resolvePackages();

        if ($interactive && !$disable_delay_msg) {
            // show install or build options in terminal with beautiful output
            $this->printInstallerInfo();

            InteractiveTerm::notice('Build process will start after 2s ...');
            sleep(2);
            echo PHP_EOL;
        }

        // check download
        if ($this->download) {
            $downloaderOptions = DownloaderOptions::extractFromConsoleOptions($this->options, 'dl');
            $downloader = new ArtifactDownloader([...$downloaderOptions, 'source-only' => implode(',', array_map(fn ($x) => $x->getName(), $this->build_packages))]);
            $downloader->addArtifacts($this->getArtifacts())->download($interactive);
        } else {
            logger()->notice('Skipping download (--no-download option enabled)');
        }

        // extract sources
        $this->extractSourceArtifacts(interactive: $interactive);

        // validate packages
        foreach ($this->packages as $package) {
            // 1. call validate package
            $package->validatePackage();
        }

        // build/install packages
        if ($interactive) {
            InteractiveTerm::notice('Building/Installing packages ...');
            keyboard_interrupt_register(function () {
                InteractiveTerm::finish('Build/Install process interrupted by user!', false);
                exit(130);
            });
        }
        $builder = ApplicationContext::get(PackageBuilder::class);
        foreach ($this->packages as $package) {
            if ($this->isBuildPackage($package) || $package instanceof LibraryPackage && $package->hasStage('build')) {
                if ($interactive) {
                    InteractiveTerm::indicateProgress('Building package: ' . ConsoleColor::yellow($package->getName()));
                }
                try {
                    /** @var LibraryPackage $package */
                    $status = $builder->buildPackage($package, $this->isBuildPackage($package));
                } catch (\Throwable $e) {
                    if ($interactive) {
                        InteractiveTerm::finish('Building package failed: ' . ConsoleColor::red($package->getName()), false);
                        echo PHP_EOL;
                    }
                    throw $e;
                }
                if ($interactive) {
                    InteractiveTerm::finish('Built package: ' . ConsoleColor::green($package->getName()) . ($status === SPC_STATUS_ALREADY_BUILT ? ' (already built, skipped)' : ''));
                }
            } elseif ($package instanceof LibraryPackage && $package->getArtifact()->shouldUseBinary()) {
                // install binary
                if ($interactive) {
                    InteractiveTerm::indicateProgress('Installing package: ' . ConsoleColor::yellow($package->getName()));
                }
                try {
                    $status = $this->installBinary($package);
                } catch (\Throwable $e) {
                    if ($interactive) {
                        InteractiveTerm::finish('Installing binary package failed: ' . ConsoleColor::red($package->getName()), false);
                        echo PHP_EOL;
                    }
                    throw $e;
                }
                if ($interactive) {
                    InteractiveTerm::finish('Installed binary package: ' . ConsoleColor::green($package->getName()) . ($status === SPC_STATUS_ALREADY_INSTALLED ? ' (already installed, skipped)' : ''));
                }
            } elseif ($package instanceof LibraryPackage) {
                throw new WrongUsageException("Package '{$package->getName()}' cannot be installed: no build stage defined and no binary artifact available for current OS.");
            }
        }
    }

    public function isBuildPackage(Package|string $package): bool
    {
        return isset($this->build_packages[is_string($package) ? $package : $package->getName()]);
    }

    /**
     * Get all resolved packages.
     * You can filter by package type class if needed.
     *
     * @template T
     * @param  class-string<T> $package_type Filter by package type
     * @return array<T>
     */
    public function getResolvedPackages(mixed $package_type = Package::class): array
    {
        return array_filter($this->packages, function (Package $pkg) use ($package_type): bool {
            return $pkg instanceof $package_type;
        });
    }

    public function isPackageResolved(string $package_name): bool
    {
        return isset($this->packages[$package_name]);
    }

    /**
     * Returns the download status of all artifacts for the resolved packages.
     *
     * @return array<string, array{
     *     source-downloaded: bool,
     *     binary-downloaded: bool,
     *     has-source: bool,
     *     has-binary: bool
     * }> artifact name => [source downloaded, binary downloaded]
     */
    public function getArtifactDownloadStatus(): array
    {
        $download_status = [];
        foreach ($this->getResolvedPackages() as $package) {
            if (($artifact = $package->getArtifact()) !== null && !isset($download_status[$artifact->getName()])) {
                // [0: source, 1: binary for current OS]
                $download_status[$artifact->getName()] = [
                    'source-downloaded' => $artifact->isSourceDownloaded(),
                    'binary-downloaded' => $artifact->isBinaryDownloaded(),
                    'has-source' => $artifact->hasSource(),
                    'has-binary' => $artifact->hasPlatformBinary(),
                ];
                $download_status[$artifact->getName()] = [$artifact->isSourceDownloaded(), $artifact->isBinaryDownloaded()];
            }
        }
        return $download_status;
    }

    /**
     * Get all artifacts from resolved and build packages.
     *
     * @return Artifact[]
     */
    public function getArtifacts(): array
    {
        $artifacts = [];
        foreach ($this->getResolvedPackages() as $package) {
            // Validate package artifacts
            $this->validatePackageArtifact($package);
            if (($artifact = $package->getArtifact()) !== null && !in_array($artifact, $artifacts, true)) {
                $artifacts[] = $artifact;
            }
        }
        // add target artifacts
        foreach ($this->build_packages as $package) {
            // Validate package artifacts
            $this->validatePackageArtifact($package);
            if (($artifact = $package->getArtifact()) !== null && !in_array($artifact, $artifacts, true)) {
                $artifacts[] = $artifact;
            }
        }
        return $artifacts;
    }

    /**
     * Extract all artifacts for resolved packages.
     */
    public function extractSourceArtifacts(bool $interactive = true): void
    {
        $packages = array_values($this->packages);

        $cache = ApplicationContext::get(ArtifactCache::class);
        $extractor = new ArtifactExtractor($cache);

        // Collect all unique artifacts
        $artifacts = [];
        $pkg_artifact_map = [];
        foreach ($packages as $package) {
            $artifact = $package->getArtifact();
            if ($artifact !== null && !isset($artifacts[$artifact->getName()]) && (!$artifact->shouldUseBinary() || $this->isBuildPackage($package))) {
                $pkg_artifact_map[$package->getName()] = $artifact->getName();
                $artifacts[$artifact->getName()] = $artifact;
            }
        }

        // Sort: php-src should be extracted first (extensions depend on it)
        uksort($artifacts, function (string $a, string $b): int {
            if ($a === 'php-src') {
                return -1;
            }
            if ($b === 'php-src') {
                return 1;
            }
            return 0;
        });

        if (count($artifacts) === 0) {
            return;
        }

        // Extract each artifact
        if ($interactive) {
            InteractiveTerm::notice('Extracting source for ' . count($artifacts) . ' artifacts: ' . implode(',', array_map(fn ($x) => ConsoleColor::yellow($x->getName()), $artifacts)) . ' ...');
            InteractiveTerm::indicateProgress('Extracting artifacts');
        }

        try {
            V2CompatLayer::beforeExtsExtractHook();
            foreach ($artifacts as $artifact) {
                if ($interactive) {
                    InteractiveTerm::setMessage('Extracting source: ' . ConsoleColor::green($artifact->getName()));
                }
                if (($pkg = array_search($artifact->getName(), $pkg_artifact_map, true)) !== false) {
                    V2CompatLayer::beforeLibExtractHook($pkg);
                }
                $extractor->extract($artifact, true);
                if (($pkg = array_search($artifact->getName(), $pkg_artifact_map, true)) !== false) {
                    V2CompatLayer::afterLibExtractHook($pkg);
                }
            }
            V2CompatLayer::afterExtsExtractHook();
            if ($interactive) {
                InteractiveTerm::finish('Extracted all sources successfully.');
                echo PHP_EOL;
            }
        } catch (\Throwable $e) {
            if ($interactive) {
                InteractiveTerm::finish('Artifact extraction failed!', false);
                echo PHP_EOL;
            }
            throw $e;
        }
    }

    public function installBinary(Package $package): int
    {
        $extractor = new ArtifactExtractor(ApplicationContext::get(ArtifactCache::class));
        $artifact = $package->getArtifact();
        if ($artifact === null || !$artifact->shouldUseBinary()) {
            throw new WrongUsageException("Package '{$package->getName()}' does not have a binary artifact to install.");
        }

        $status = $extractor->extract($artifact);
        if ($status === SPC_STATUS_ALREADY_EXTRACTED) {
            return SPC_STATUS_ALREADY_INSTALLED;
        }

        // perform package after-install actions
        $this->performAfterInstallActions($package);
        return SPC_STATUS_INSTALLED;
    }

    public function getPackage(string $package_name): ?Package
    {
        return $this->packages[$package_name] ?? null;
    }

    /**
     * Validate that a package has required artifacts.
     *
     * @throws WrongUsageException if target/library package has no source or platform binary
     */
    private function validatePackageArtifact(Package $package): void
    {
        // target and library must have at least source or platform binary
        if (in_array($package->getType(), ['library', 'target']) && !$package->getArtifact()?->hasSource() && !$package->getArtifact()?->hasPlatformBinary()) {
            throw new WrongUsageException("Validation failed: Target package '{$package->getName()}' has no source or platform binary defined.");
        }
    }

    private function resolvePackages(): void
    {
        $pkgs = [];

        foreach ($this->build_packages as $package) {
            // call #[ResolveBuild] annotation methods if defined
            if ($package instanceof TargetPackage && is_array($deps = $package->_emitResolveBuild($this))) {
                $this->target_additional_dependencies[$package->getName()] = $deps;
            }
            $pkgs[] = $package->getName();
        }

        // gather install packages
        foreach ($this->install_packages as $package) {
            $pkgs[] = $package->getName();
        }

        // resolve dependencies string
        $resolved_packages = DependencyResolver::resolve(
            $pkgs,
            $this->target_additional_dependencies,
            $this->options['with-suggests'] ?? false
        );

        foreach ($resolved_packages as $pkg_name) {
            $this->packages[$pkg_name] = PackageLoader::getPackage($pkg_name);
        }
    }

    private function handlePhpTargetPackage(TargetPackage $package): void
    {
        // process 'php' target
        if ($package->getName() === 'php') {
            logger()->warning("Building 'php' target is deprecated, please use specific targets like 'build:php-cli' instead.");

            $added = false;

            if ($package->getBuildOption('build-all') || $package->getBuildOption('build-cli')) {
                $cli = PackageLoader::getPackage('php-cli');
                $this->build_packages[$cli->getName()] = $cli;
                $added = true;
            }
            if ($package->getBuildOption('build-all') || $package->getBuildOption('build-fpm')) {
                $fpm = PackageLoader::getPackage('php-fpm');
                $this->build_packages[$fpm->getName()] = $fpm;
                $added = true;
            }
            if ($package->getBuildOption('build-all') || $package->getBuildOption('build-micro')) {
                $micro = PackageLoader::getPackage('php-micro');
                $this->build_packages[$micro->getName()] = $micro;
                $added = true;
            }
            if ($package->getBuildOption('build-all') || $package->getBuildOption('build-cgi')) {
                $cgi = PackageLoader::getPackage('php-cgi');
                $this->build_packages[$cgi->getName()] = $cgi;
                $added = true;
            }
            if ($package->getBuildOption('build-all') || $package->getBuildOption('build-embed')) {
                $embed = PackageLoader::getPackage('php-embed');
                $this->build_packages[$embed->getName()] = $embed;
                $added = true;
            }
            if ($package->getBuildOption('build-all') || $package->getBuildOption('build-frankenphp')) {
                $frankenphp = PackageLoader::getPackage('frankenphp');
                $this->build_packages[$frankenphp->getName()] = $frankenphp;
                $added = true;
            }
            $this->build_packages[$package->getName()] = $package;

            if (!$added) {
                throw new WrongUsageException(
                    "No SAPI target specified to build. Please use '--build-cli', '--build-fpm', '--build-micro', " .
                    "'--build-cgi', '--build-embed', '--build-frankenphp' or '--build-all' options."
                );
            }
        } else {
            // process specific php sapi targets
            $this->build_packages['php'] = PackageLoader::getPackage('php');
            $this->build_packages[$package->getName()] = $package;
        }
    }

    private function printInstallerInfo(): void
    {
        InteractiveTerm::notice('Installation summary:');
        $summary['Packages to be built'] = implode(',', array_map(fn ($x) => $x->getName(), array_values($this->build_packages)));
        $summary['Packages to be installed'] = implode(',', array_map(fn ($x) => $x->getName(), array_values($this->packages)));
        $summary['Artifacts to be downloaded'] = implode(',', array_map(fn ($x) => $x->getName(), $this->getArtifacts()));
        $this->printArrayInfo(array_filter($summary));
        echo PHP_EOL;

        foreach ($this->build_packages as $package) {
            $info = $package->getPackageInfo();
            if ($info === []) {
                continue;
            }
            InteractiveTerm::notice("{$package->getName()} build options:");
            // calculate space count for every line
            $this->printArrayInfo($info);
            echo PHP_EOL;
        }
    }

    private function printArrayInfo(array $info): void
    {
        $maxlen = 0;
        foreach ($info as $k => $v) {
            $maxlen = max(strlen($k), $maxlen);
        }
        foreach ($info as $k => $v) {
            if (is_string($v)) {
                InteractiveTerm::plain("  {$k}: " . str_pad('', $maxlen - strlen($k)) . ConsoleColor::yellow($v));
            } elseif (is_array($v) && !is_assoc_array($v)) {
                $first = array_shift($v);
                InteractiveTerm::plain("  {$k}: " . str_pad('', $maxlen - strlen($k)) . ConsoleColor::yellow($first));
                foreach ($v as $vs) {
                    InteractiveTerm::plain(str_pad('', $maxlen + 4) . ConsoleColor::yellow($vs));
                }
            }
        }
    }

    private function performAfterInstallActions(Package $package): void
    {
        // ----------- perform post-install actions from extracted .package.{pkg_name}.postinstall.json -----------
        $root_dir = ($package->getArtifact()?->getBinaryDir() ?? '') !== '' ? $package->getArtifact()?->getBinaryDir() : null;
        if ($root_dir !== null) {
            $action_json = "{$root_dir}/.package.{$package->getName()}.postinstall.json";
            if (is_file($action_json)) {
                $action_json = json_decode(file_get_contents($action_json), true);
                if (!is_array($action_json)) {
                    throw new WrongUsageException("Invalid post-install action JSON format for package '{$package->getName()}'.");
                }
                $placeholders = get_pack_replace();
                foreach ($action_json as $action) {
                    $action_name = $action['action'] ?? '';
                    switch ($action_name) {
                        // replace-path: => files: [relative_path1, relative_path2]
                        case 'replace-path':
                            $files = $action['files'] ?? [];
                            foreach ($files as $file) {
                                $filepath = $root_dir . "/{$file}";
                                FileSystem::replaceFileStr($filepath, array_values($placeholders), array_keys($placeholders));
                            }
                            break;
                            // replace-to-env: => file: "relative_path", search: "SEARCH_STR", replace-env: "ENV_VAR_NAME"
                        case 'replace-to-env':
                            $file = $action['file'] ?? '';
                            $search = $action['search'] ?? '';
                            $env_var = $action['replace-env'] ?? '';
                            $replace = getenv($env_var) ?: '';
                            $filepath = $root_dir . "/{$file}";
                            FileSystem::replaceFileStr($filepath, $search, $replace);
                            break;
                        default:
                            throw new WrongUsageException("Unknown post-install action '{$action_name}' for package '{$package->getName()}'.");
                    }
                }
                // remove the action file after processing
                unlink($root_dir . "/.package.{$package->getName()}.postinstall.json");
            }
        }
    }
}
