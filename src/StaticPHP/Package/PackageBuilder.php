<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Runtime\Shell\Shell;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use StaticPHP\Util\System\LinuxUtil;

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

    /**
     * Deploy the binary file from src to dst.
     */
    public function deployBinary(string $src, string $dst, bool $executable = true): string
    {
        logger()->debug("Deploying binary from {$src} to {$dst}");

        // file must exists
        if (!file_exists($src)) {
            throw new SPCInternalException("Deploy failed. Cannot find file: {$src}");
        }
        // dst dir must exists
        FileSystem::createDir(dirname($dst));

        // ignore copy to self
        if (realpath($src) !== realpath($dst)) {
            FileSystem::copy($src, $dst);
            if ($executable) {
                chmod($dst, 0755);
            }
        }

        // file exist
        if (!file_exists($dst)) {
            throw new SPCInternalException("Deploy failed. Cannot find file after copy: {$dst}");
        }

        // extract debug info
        $this->extractDebugInfo($dst);

        // strip
        if (!$this->getOption('no-strip') && SystemTarget::isUnix()) {
            $this->stripBinary($dst);
        }

        // UPX for linux
        $upx_option = $this->getOption('with-upx-pack');
        if ($upx_option && SystemTarget::getTargetOS() === 'Linux' && $executable) {
            if ($this->getOption('no-strip')) {
                logger()->warning('UPX compression is not recommended when --no-strip is enabled.');
            }
            logger()->info("Compressing {$dst} with UPX");
            shell()->exec(getenv('UPX_EXEC') . " --best {$dst}");
        } elseif ($upx_option && SystemTarget::getTargetOS() === 'Windows' && $executable) {
            logger()->info("Compressing {$dst} with UPX");
            shell()->exec(getenv('UPX_EXEC') . ' --best ' . escapeshellarg($dst));
        }

        return $dst;
    }

    /**
     * Extract debug information from binary file.
     *
     * @param string $binary_path the path to the binary file, including executables, shared libraries, etc
     */
    public function extractDebugInfo(string $binary_path): string
    {
        $target_dir = BUILD_ROOT_PATH . '/debug';
        $basename = basename($binary_path);
        $debug_file = "{$target_dir}/{$basename}" . (SystemTarget::getTargetOS() === 'Darwin' ? '.dwarf' : '.debug');
        if (SystemTarget::getTargetOS() === 'Darwin') {
            FileSystem::createDir($target_dir);
            shell()->exec("dsymutil -f {$binary_path} -o {$debug_file}");
        } elseif (SystemTarget::getTargetOS() === 'Linux') {
            FileSystem::createDir($target_dir);
            if ($eu_strip = LinuxUtil::findCommand('eu-strip')) {
                shell()
                    ->exec("{$eu_strip} -f {$debug_file} {$binary_path}")
                    ->exec("objcopy --add-gnu-debuglink={$debug_file} {$binary_path}");
            } else {
                shell()
                    ->exec("objcopy --only-keep-debug {$binary_path} {$debug_file}")
                    ->exec("objcopy --add-gnu-debuglink={$debug_file} {$binary_path}");
            }
        } else {
            logger()->debug('extractDebugInfo is only supported on Linux and macOS');
            return '';
        }
        return $debug_file;
    }

    /**
     * Strip unneeded symbols from binary file.
     */
    public function stripBinary(string $binary_path): void
    {
        shell()->exec(match (SystemTarget::getTargetOS()) {
            'Darwin' => "strip -S {$binary_path}",
            'Linux' => "strip --strip-unneeded {$binary_path}",
            default => throw new SPCInternalException('stripBinary is only supported on Linux and macOS'),
        });
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
