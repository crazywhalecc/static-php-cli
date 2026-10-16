<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\SPCException;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Registry\PackageLoader;
use StaticPHP\Runtime\Shell\Shell;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Toolchain\ToolchainManager;
use StaticPHP\Toolchain\ZigToolchain;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\GlobalPathTrait;
use StaticPHP\Util\InteractiveTerm;

class PackageBuilder
{
    use GlobalPathTrait;

    /** @var int make jobs count */
    public readonly int $concurrency;

    /** @var array<string, mixed> Build arguments */
    protected array $arguments = [];

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
        if (!$package instanceof LibraryPackage && !$package instanceof ToolPackage) {
            throw new SPCInternalException('Please, never try to build non-library, non-tool packages directly.');
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

        try {
            if ($package->getType() !== 'virtual-target') {
                // patch before build
                $package->emitPatchBeforeBuild();
            }

            // build
            $package->runStage('build');

            if ($package->getType() !== 'virtual-target') {
                // install license
                if (($license = PackageConfig::get($package->getName(), 'license')) !== null) {
                    $this->installLicense($package, $license);
                }
            }

            // Record the installed version for tool packages built from source, so
            // `check-update --installed` reflects what's actually on disk (mirrors the
            // binary-install recording in PackageInstaller::installBinary()).
            if ($package instanceof ToolPackage) {
                $source_info = ApplicationContext::get(ArtifactCache::class)->getSourceInfo($package->getName());
                ToolVersionRegistry::record($package->getName(), $source_info['version'] ?? null);
            }

            // Record the fingerprint of the inputs that produced these outputs, so a later run can
            // tell whether this package is still up to date (a no-op without a declared stamp).
            $package->writeInstallStamp();
        } catch (SPCException $e) {
            // Ensure package information is bound if not already
            if ($e->getPackageInfo() === null) {
                $e->bindPackageInfo([
                    'package_name' => $package->name,
                    'package_type' => $package->type,
                    'package_class' => get_class($package),
                    'file' => null,
                    'line' => null,
                ]);
            }
            throw $e;
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

    public function setArgument(string $key, mixed $value): void
    {
        $this->arguments[$key] = $value;
    }

    public function getArgument(string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
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

        if (!$this->getOption('no-strip') && SystemTarget::isUnix()) {
            // extract debug info
            $this->extractDebugInfo($dst);
            // strip binary
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
            cmd()->exec(getenv('UPX_EXEC') . ' --best ' . escapeshellarg($dst));
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
        FileSystem::createDir($target_dir);
        $basename = basename($binary_path);
        $debug_file = "{$target_dir}/{$basename}" . (SystemTarget::getTargetOS() === 'Darwin' ? '.dwarf' : '.debug');
        if (SystemTarget::getTargetOS() === 'Darwin') {
            shell()->exec("dsymutil -f {$binary_path} -o {$debug_file}");
        } elseif (SystemTarget::getTargetOS() === 'Linux') {
            $objcopy = $this->getBinUtil('objcopy');
            try {
                FileSystem::copy($binary_path, $debug_file);
                shell()
                    ->exec("{$objcopy} --strip-debug {$binary_path}")
                    ->exec("{$objcopy} --add-gnu-debuglink={$debug_file} {$binary_path}");
            } catch (SPCException $e) {
                // A separate debug file is optional. Native builds do not require the LLVM binutils,
                // so degrade to "no debug file" instead of failing an otherwise good build.
                logger()->warning("Could not extract debug info from '{$binary_path}' with '{$objcopy}': {$e->getMessage()}");
                logger()->warning('Continuing without a separate debug file. Install the LLVM binutils (`bin/spc install-pkg llvm-tools`) to keep debug information.');
                if (is_file($debug_file)) {
                    unlink($debug_file); // do not leave a debug file the build did not register
                }
                return '';
            }
        } else {
            logger()->debug('extractDebugInfo is only supported on Linux and macOS');
            return '';
        }
        return $debug_file;
    }

    /**
     * Strip unneeded symbols from binary file.
     *
     * Under the zig toolchain the LLVM binutils are only required for cross builds (see
     * ZigToolchain::getRequiredPackages()). Native builds use the host strip, which can choke on
     * zig/LLVM bitcode, and a build must not fail because of that: skipping strip only costs
     * binary size.
     */
    public function stripBinary(string $binary_path): void
    {
        $strip = $this->getBinUtil('strip');
        $cmd = match (SystemTarget::getTargetOS()) {
            'Darwin' => "{$strip} -S {$binary_path}",
            'Linux' => "{$strip} --strip-unneeded {$binary_path}",
            'Windows' => 'echo "Skip strip on Windows"', // Windows strip is not available for now
            default => throw new SPCInternalException('stripBinary is only supported on Linux and macOS'),
        };
        try {
            shell()->exec($cmd);
        } catch (SPCException $e) {
            if (!$this->shouldAdviseLlvmTools()) {
                throw $e;
            }
            logger()->warning("Failed to strip '{$binary_path}' with '{$strip}': {$e->getMessage()}");
            logger()->warning('Continuing without strip (this only affects binary size). Install the LLVM binutils (`bin/spc install-pkg llvm-tools`) if it keeps happening.');
        }
    }

    /**
     * True when the LLVM binutils would help but are not installed: the zig toolchain is active
     * and the llvm-tools package is missing (optional for native builds; a cross build that first
     * had to install zig only gets it on its next run).
     *
     * Guarded because a custom registry does not have to ship `llvm-tools`; in that case there is
     * nothing to advise and the original strip error has to surface unchanged.
     */
    private function shouldAdviseLlvmTools(): bool
    {
        if (ToolchainManager::getToolchainClass() !== ZigToolchain::class) {
            return false;
        }
        try {
            $llvm_tools = PackageLoader::getPackage('llvm-tools');
        } catch (WrongUsageException) {
            return false;
        }
        return !($llvm_tools instanceof ToolPackage && $llvm_tools->isInstalled());
    }

    private function getBinUtil(string $name): string
    {
        $env = getenv(strtoupper($name)) ?: '';
        $is_zig = ToolchainManager::getToolchainClass() === ZigToolchain::class;

        // Zig ships zig-<tool> wrappers for its own binutils, but they only implement a subset of
        // the options. Treat them as absent so these post-processing steps use llvm-tools when
        // installed and the host binutils otherwise, which is what lets native builds work
        // without the LLVM binutils.
        if ($is_zig && $env === "zig-{$name}") {
            $env = '';
        }

        // Honor an explicit override.
        if ($env !== '') {
            return $env;
        }

        if ($is_zig) {
            $llvm_tools = PackageLoader::getPackage('llvm-tools');
            if ($llvm_tools instanceof ToolPackage && $llvm_tools->isInstalled()) {
                return $llvm_tools->getBinary("llvm-{$name}");
            }
            // Optional for native builds: make it visible which binutil the build actually uses.
            logger()->debug("llvm-tools is not installed; falling back to the host '{$name}' (install the LLVM binutils with `bin/spc install-pkg llvm-tools`).");
        }

        return $name;
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
