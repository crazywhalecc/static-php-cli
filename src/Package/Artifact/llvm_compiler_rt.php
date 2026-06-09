<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Artifact\Downloader\Type\CheckUpdateResult;
use StaticPHP\Artifact\Downloader\Type\GitHubTokenSetupTrait;
use StaticPHP\Attribute\Artifact\AfterBinaryExtract;
use StaticPHP\Attribute\Artifact\CustomBinary;
use StaticPHP\Attribute\Artifact\CustomBinaryCheckUpdate;
use StaticPHP\Exception\BuildFailureException;
use StaticPHP\Exception\DownloaderException;
use StaticPHP\Runtime\SystemTarget;

/**
 * Builds the compiler-rt bits zig ships without — libclang_rt.profile.a (PGO instrumentation)
 * and clang_rt.crtbegin.o/crtend.o (__dso_handle for shared libs). Target-arch specific:
 * libs land in PKG_ROOT_PATH/zig/lib/{triple}.
 * Also builds libclang_rt.cpu_model.a (__cpu_model / __cpu_indicator_init, the libgcc-compatible
 * globals that __builtin_cpu_supports()/__builtin_cpu_init() reference) for arches that have it.
 */
class llvm_compiler_rt
{
    use GitHubTokenSetupTrait;

    #[CustomBinary('llvm-compiler-rt', [
        'linux-x86_64',
        'linux-aarch64',
        'macos-x86_64',
        'macos-aarch64',
    ])]
    public function downBinary(ArtifactDownloader $downloader): DownloadResult
    {
        $llvmVersion = $this->detectZigLlvmVersion()
            ?? throw new DownloaderException('llvm-compiler-rt: could not detect bundled clang version from zig cc --version');
        $tarball = "compiler-rt-{$llvmVersion}.src.tar.xz";
        $url = "https://github.com/llvm/llvm-project/releases/download/llvmorg-{$llvmVersion}/{$tarball}";
        $tarballPath = DOWNLOAD_PATH . '/' . $tarball;
        default_shell()->executeCurlDownload($url, $tarballPath, headers: $this->getGitHubTokenHeaders(), retries: $downloader->getRetry());
        return DownloadResult::archive($tarball, ['url' => $url, 'version' => $llvmVersion], extract: '{source_path}/llvm-compiler-rt', verified: false, version: $llvmVersion);
    }

    #[CustomBinaryCheckUpdate('llvm-compiler-rt', [
        'linux-x86_64',
        'linux-aarch64',
        'macos-x86_64',
        'macos-aarch64',
    ])]
    public function checkUpdateBinary(?string $old_version, ArtifactDownloader $downloader): CheckUpdateResult
    {
        $llvmVersion = $this->detectZigLlvmVersion()
            ?? throw new DownloaderException('llvm-compiler-rt: could not detect bundled clang version from zig cc --version');
        return new CheckUpdateResult(
            old: $old_version,
            new: $llvmVersion,
            needUpdate: $old_version === null || $llvmVersion !== $old_version,
        );
    }

    #[AfterBinaryExtract('llvm-compiler-rt', [
        'linux-x86_64',
        'linux-aarch64',
        'macos-x86_64',
        'macos-aarch64',
    ])]
    public function postExtract(string $target_path): void
    {
        $this->buildForTriple($target_path);
    }

    public function buildForTriple(?string $sourceDir = null, ?string $triple = null): void
    {
        $sourceDir ??= SOURCE_PATH . '/llvm-compiler-rt';
        $triple ??= SystemTarget::getCanonicalTriple();
        $libDir = zig::path() . '/lib/' . $triple;
        if ($this->isBuilt($libDir)) {
            return;
        }
        if (!is_dir($sourceDir) || !is_dir("{$sourceDir}/lib/profile")) {
            throw new BuildFailureException("llvm-compiler-rt: missing source at {$sourceDir} (extraction layout changed?)");
        }
        f_mkdir($libDir, recursive: true);
        $profileLib = "{$libDir}/libclang_rt.profile.a";
        $crtBegin = "{$libDir}/clang_rt.crtbegin.o";
        $crtEnd = "{$libDir}/clang_rt.crtend.o";
        if (!file_exists($profileLib)) {
            $this->buildProfileRuntime($sourceDir, $profileLib, $triple);
        }
        if (!file_exists($crtBegin) || !file_exists($crtEnd)) {
            $this->buildCrtObjects($sourceDir, $crtBegin, $crtEnd, $triple);
        }
        $cpuModelLib = "{$libDir}/libclang_rt.cpu_model.a";
        if (self::cpuModelArch($triple) !== null && !file_exists($cpuModelLib)) {
            $this->buildCpuModelBuiltins($sourceDir, $cpuModelLib, $triple);
        }
    }

    public function isBuilt(string $libDir): bool
    {
        return file_exists("{$libDir}/libclang_rt.profile.a")
            && file_exists("{$libDir}/clang_rt.crtbegin.o")
            && file_exists("{$libDir}/clang_rt.crtend.o")
            && (self::cpuModelArch(basename($libDir)) === null || file_exists("{$libDir}/libclang_rt.cpu_model.a"));
    }

    private function detectZigLlvmVersion(): ?string
    {
        if (!zig::isInstalled()) {
            return null;
        }
        [$rc, $out] = shell()->execWithResult(escapeshellarg(zig::binary()) . ' cc --version', false);
        if ($rc !== 0) {
            return null;
        }
        return preg_match('/clang version (\d+\.\d+\.\d+)/', implode("\n", $out), $m) ? $m[1] : null;
    }

    private function buildProfileRuntime(string $srcRoot, string $libPath, string $triple): void
    {
        $profileSrc = "{$srcRoot}/lib/profile";
        $profileInc = "{$srcRoot}/include";
        // Skip OS-specific sources we can't satisfy without their SDKs.
        $skip = ['/PlatformAIX', '/PlatformDarwin', '/PlatformFuchsia', '/PlatformOther', '/PlatformWindows', '/WindowsMMap'];
        $sources = array_filter(
            array_merge(glob("{$profileSrc}/*.c") ?: [], glob("{$profileSrc}/*.cpp") ?: []),
            fn ($f) => !array_any($skip, fn ($s) => str_contains($f, $s)),
        );

        $objDir = "{$srcRoot}/obj-profile-{$triple}";
        f_mkdir($objDir, recursive: true);
        $cflags = "-target {$triple} -c -O2 -fPIC -fvisibility=hidden "
            . '-I' . escapeshellarg($profileInc) . ' '
            . '-DCOMPILER_RT_HAS_ATOMICS=1 -DCOMPILER_RT_HAS_FCNTL_LCK=1 -DCOMPILER_RT_HAS_UNAME=1';
        $srcArgs = implode(' ', array_map('escapeshellarg', $sources));
        shell()->cd($objDir)->exec("zig cc {$cflags} {$srcArgs}");
        shell()->cd($objDir)->exec('zig ar rcs ' . escapeshellarg($libPath) . ' *.o');
    }

    private function buildCrtObjects(string $srcRoot, string $crtBegin, string $crtEnd, string $triple): void
    {
        $beginSrc = "{$srcRoot}/lib/builtins/crtbegin.c";
        $endSrc = "{$srcRoot}/lib/builtins/crtend.c";
        if (!is_file($beginSrc) || !is_file($endSrc)) {
            throw new BuildFailureException("llvm-compiler-rt: crtbegin/crtend source missing under {$srcRoot}/lib/builtins");
        }
        $cflags = "-target {$triple} -c -O2 -fPIC -fvisibility=hidden -DCRT_HAS_INITFINI_ARRAY";
        foreach ([[$beginSrc, $crtBegin], [$endSrc, $crtEnd]] as [$src, $dst]) {
            shell()->exec("zig cc {$cflags} -o " . escapeshellarg($dst) . ' ' . escapeshellarg($src));
        }
    }

    /**
     * Build libclang_rt.cpu_model.a, provides
     * the globals that __builtin_cpu_supports() reference.
     */
    private function buildCpuModelBuiltins(string $srcRoot, string $libPath, string $triple): void
    {
        $builtins = "{$srcRoot}/lib/builtins";
        $family = self::cpuModelArch($triple);
        $cpuModelDir = "{$builtins}/cpu_model";
        if (is_dir($cpuModelDir)) {
            $src = "{$cpuModelDir}/{$family}.c";
            $includes = '-I' . escapeshellarg($builtins) . ' -I' . escapeshellarg($cpuModelDir);
        } else {
            $src = "{$builtins}/cpu_model.c";
            $includes = '-I' . escapeshellarg($builtins);
        }
        if (!is_file($src)) {
            throw new BuildFailureException("llvm-compiler-rt: cpu_model source not found for {$triple} under {$builtins}");
        }

        $objDir = "{$srcRoot}/obj-cpu-model-{$triple}";
        f_mkdir($objDir, recursive: true);
        $obj = "{$objDir}/cpu_model.o";
        $cflags = "-target {$triple} -c -O2 -fPIC {$includes}";
        shell()->exec('zig cc ' . $cflags . ' -o ' . escapeshellarg($obj) . ' ' . escapeshellarg($src));
        shell()->exec('zig ar rcs ' . escapeshellarg($libPath) . ' ' . escapeshellarg($obj));
    }

    private static function cpuModelArch(string $triple): ?string
    {
        $arch = explode('-', $triple)[0];
        return match (true) {
            in_array($arch, ['x86_64', 'amd64', 'i386', 'i686', 'x86'], true) => 'x86',
            in_array($arch, ['aarch64', 'arm64'], true) => 'aarch64',
            str_starts_with($arch, 'riscv') => 'riscv',
            default => null,
        };
    }
}
