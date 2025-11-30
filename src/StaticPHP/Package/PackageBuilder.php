<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Runtime\Shell\Shell;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\GlobalEnvManager;
use StaticPHP\Util\InteractiveTerm;

class PackageBuilder
{
    /** @var int make jobs count */
    public readonly int $concurrency;

    /**
     * @param array $options Builder options
     */
    public function __construct(protected array $options = [])
    {
        ApplicationContext::set(PackageBuilder::class, $this);

        // apply build toolchain envs
        GlobalEnvManager::afterInit();

        $this->concurrency = (int) getenv('SPC_CONCURRENCY') ?: 1;
    }

    public function buildPackage(Package $package, bool $force = false): int
    {
        // init build dirs
        if (!$package instanceof LibraryPackage) {
            throw new SPCInternalException('Please, never try to build non-library packages directly.');
        }
        FileSystem::createDir($package->getBuildRootPath());
        FileSystem::createDir($package->getIncludeDir());
        FileSystem::createDir($package->getBinDir());
        FileSystem::createDir($package->getLibDir());

        if (!$package->hasStage('build')) {
            throw new WrongUsageException("Package '{$package->name}' does not have a current platform 'build' stage defined.");
        }

        // validate package should be built
        if (!$force) {
            return $package->isInstalled() ? SPC_STATUS_ALREADY_BUILT : $this->buildPackage($package, true);
        }
        // check source is ready
        if ($package->getType() !== 'virtual-target' && !is_dir($package->getSourceDir())) {
            throw new WrongUsageException("Source directory for package '{$package->name}' does not exist. Please fetch the source before building.");
        }
        Shell::passthruCallback(function () {
            InteractiveTerm::advance();
        });

        if ($package->getType() !== 'virtual-target') {
            // patch before build
            $package->patchBeforeBuild();
        }

        // build
        $package->runStage('build');

        if ($package->getType() !== 'virtual-target') {
            // install license
            if (($license = PackageConfig::get($package->getName(), 'license')) !== null) {
                $this->installLicense($package, $license);
            }
        }
        return SPC_STATUS_BUILT;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    private function installLicense(Package $package, array $license): void
    {
        $dir = BUILD_ROOT_PATH . '/source-licenses/' . $package->getName();
        FileSystem::createDir($dir);
        if (is_assoc_array($license)) {
            $license = [$license];
        }

        foreach ($license as $index => $item) {
            if ($item['type'] === 'text') {
                FileSystem::writeFile("{$dir}/{$index}.txt", $item['text']);
            } elseif ($item['type'] === 'file') {
                FileSystem::copy("{$package->getSourceDir()}/{$item['path']}", "{$dir}/{$index}.txt");
            }
        }
    }
}
