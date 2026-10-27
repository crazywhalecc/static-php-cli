<?php

declare(strict_types=1);

namespace StaticPHP\Toolchain;

use Package\Artifact\zig;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\ToolPackage;
use StaticPHP\Registry\PackageLoader;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Toolchain\Interface\PackageRequirementsInterface;
use StaticPHP\Toolchain\Interface\UnixToolchainInterface;
use StaticPHP\Util\GlobalEnvManager;
use StaticPHP\Util\System\LinuxUtil;

class ZigToolchain implements UnixToolchainInterface, PackageRequirementsInterface
{
    /** Memoised clang version of the zig binary; resolved at most once per run. */
    private ?string $compiler_version = null;

    private bool $compiler_version_resolved = false;

    public function initEnv(): void
    {
        // Set environment variables for zig toolchain
        GlobalEnvManager::putenv('SPC_DEFAULT_CC=zig-cc');
        GlobalEnvManager::putenv('SPC_DEFAULT_CXX=zig-c++');
        GlobalEnvManager::putenv('SPC_DEFAULT_AR=zig-ar');
        GlobalEnvManager::putenv('SPC_DEFAULT_RANLIB=zig-ranlib');
        GlobalEnvManager::putenv('SPC_DEFAULT_LD=zig-ld.lld');

        // zig extracts into {pkg_root}/zig (binary and wrappers), not {pkg_root}/bin, so the
        // directory has to be on PATH wherever CC=zig-cc is resolved. Done here rather than in
        // afterInit() because `spc doctor` sets SPC_SKIP_TOOLCHAIN_CHECK, which skips afterInit()
        // yet still builds packages from source (the re2c fix ran cmake with zig-cc and failed
        // with "is not a full path and was not found in the PATH"). Adding the path when zig is
        // not installed yet is harmless, and matches the other toolchains (musl, brew clang).
        GlobalEnvManager::addPathIfNotExists($this->getPath());

        $rt_dir = $this->getPath() . '/lib/' . SystemTarget::getCanonicalTriple();
        GlobalEnvManager::putenv("SPC_COMPILER_RT_DIR={$rt_dir}");
    }

    public function afterInit(): void
    {
        if (is_dir($this->getPath())) {
            zig::writeWrappers($this->getPath());
        }
        f_passthru('ulimit -n 2048'); // zig opens extra file descriptors, so when a lot of extensions are built statically, 1024 is not enough
        $cflags = getenv('SPC_DEFAULT_CFLAGS') ?: '';
        $cxxflags = getenv('SPC_DEFAULT_CXXFLAGS') ?: '';
        $extraCflags = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') ?: '';
        $cflags = trim($cflags . ' -Wno-date-time');
        $cxxflags = trim($cxxflags . ' -Wno-date-time');
        $extraCflags = trim($extraCflags . ' -Wno-date-time');
        GlobalEnvManager::putenv("SPC_DEFAULT_CFLAGS={$cflags}");
        GlobalEnvManager::putenv("SPC_DEFAULT_CXXFLAGS={$cxxflags}");
        GlobalEnvManager::putenv("SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS={$extraCflags}");
        GlobalEnvManager::putenv('RANLIB=zig-ranlib');
        GlobalEnvManager::putenv('OBJCOPY=zig-objcopy');
        $extra_libs = getenv('SPC_EXTRA_LIBS') ?: '';
        if (!str_contains($extra_libs, '-lunwind')) {
            // Add unwind library if not already present
            $extra_libs = trim($extra_libs . ' -lunwind');
            GlobalEnvManager::putenv("SPC_EXTRA_LIBS={$extra_libs}");
        }
        $cflags = getenv('SPC_DEFAULT_CFLAGS') ?: getenv('CFLAGS') ?: '';
        $has_avx512 = str_contains($cflags, '-mavx512') || str_contains($cflags, '-march=x86-64-v4');
        if (!$has_avx512) {
            $extra_vars = getenv('SPC_EXTRA_PHP_VARS') ?: '';
            GlobalEnvManager::putenv("SPC_EXTRA_PHP_VARS=php_cv_have_avx512=no php_cv_have_avx512vbmi=no {$extra_vars}");
        }
    }

    /**
     * Version of the C compiler zig drives, i.e. the clang it bundles (for example "22.1.8").
     *
     * Deliberately not the zig release version: getCompilerInfo() reports that one ("zig 0.17.0")
     * and the two numbers are unrelated. Toolchain-specific artifacts key on this value because it
     * describes the compiler whose runtime and bitcode they have to match.
     */
    public function getCompilerVersion(): ?string
    {
        if ($this->compiler_version_resolved) {
            return $this->compiler_version;
        }
        $this->compiler_version_resolved = true;

        try {
            $zig = PackageLoader::getPackage('zig');
        } catch (WrongUsageException) {
            return null;
        }
        if (!$zig instanceof ToolPackage || !$zig->isInstalled()) {
            return null;
        }

        // -dumpversion prints the same version as `cc --version`, which leaves an empty a.o in the
        // working directory (the user's project) on every run.
        [$code, $out] = shell()->execWithResult(escapeshellarg($zig->getBinary('zig')) . ' cc -dumpversion', false);
        if ($code === 0 && preg_match('/^(\d+\.\d+\.\d+)$/', trim(implode("\n", $out)), $match)) {
            $this->compiler_version = $match[1];
        }
        return $this->compiler_version;
    }

    public function getCompilerInfo(): ?string
    {
        $version = shell(false)->execWithResult('zig version', false)[1][0] ?? '';
        return trim("zig {$version}");
    }

    public function isStatic(): bool
    {
        // if SPC_LIBC is set, it means the target is static, remove it when 3.0 is released
        if ($target = getenv('SPC_TARGET')) {
            if (str_contains($target, '-macos') || str_contains($target, '-native') && PHP_OS_FAMILY === 'Darwin') {
                return false;
            }
            if (str_contains($target, '-gnu')) {
                return false;
            }
            if (str_contains($target, '-dynamic')) {
                return false;
            }
            if (str_contains($target, '-musl')) {
                return true;
            }
            if (PHP_OS_FAMILY === 'Linux') {
                return LinuxUtil::isMuslDist();
            }
            return true;
        }
        if (getenv('SPC_LIBC') === 'musl') {
            return true;
        }
        return false;
    }

    /**
     * The packages the zig toolchain needs: zig itself, the compiler runtime zig does not ship and,
     * for a cross build, the LLVM binutils.
     *
     * Declaring them here instead of in a doctor check keeps them in sync with the toolchain:
     * they join the normal install pipeline, their isInstalled() decides skip vs rebuild, and
     * llvm-compiler-rt is scoped to the canonical triple of the current target.
     *
     * @return string[]
     */
    public function getRequiredPackages(): array
    {
        // zig is a plain tool package with a hosted binary, so it can always be resolved: the
        // pipeline downloads every artifact before it installs anything, which means declaring it
        // here installs zig on a machine that never ran `spc doctor`.
        $packages = ['zig'];

        // compiler-rt and llvm-tools are versioned against the clang bundled with zig, and their
        // downloaders run zig to find out which one, so they can only be resolved once zig is on
        // disk. On a fresh machine the build that installs zig therefore defers them to the next
        // one; PackageInstaller reports that final state once per run, after the install loop.
        if ($this->checkRequirements() !== []) {
            return $packages;
        }
        // A custom registry does not have to ship either package. compiler-rt is only needed where
        // the target really has a build step (currently Linux hosts).
        if (PackageLoader::hasPackage('llvm-compiler-rt') && PackageLoader::getPackage('llvm-compiler-rt')->hasBuildFunctionForCurrentOS()) {
            $packages[] = 'llvm-compiler-rt';
        }
        // The host binutils only understand the host architecture, so stripping or splitting debug
        // info off a cross-built binary needs the LLVM ones; native builds keep using the host tools.
        if (SystemTarget::isCrossCompiling() && PackageLoader::hasPackage('llvm-tools')) {
            $packages[] = 'llvm-tools';
        }
        return $packages;
    }

    /**
     * @return string[] Unmet prerequisites, empty when the toolchain is ready to go
     */
    public function checkRequirements(): array
    {
        try {
            $zig = PackageLoader::getPackage('zig');
        } catch (WrongUsageException) {
            return ['the `zig` tool package'];
        }
        if (!$zig instanceof ToolPackage || !$zig->isInstalled()) {
            return ['the `zig` tool package'];
        }
        return [];
    }

    private function getPath(): string
    {
        return PKG_ROOT_PATH . '/zig';
    }
}
