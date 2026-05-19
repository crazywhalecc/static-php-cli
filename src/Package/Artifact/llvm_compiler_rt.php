<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Artifact\Downloader\Type\CheckUpdateResult;
use StaticPHP\Attribute\Artifact\AfterBinaryExtract;
use StaticPHP\Attribute\Artifact\CustomBinary;
use StaticPHP\Attribute\Artifact\CustomBinaryCheckUpdate;
use StaticPHP\Exception\DownloaderException;
use StaticPHP\Runtime\SystemTarget;

class llvm_compiler_rt
{
    #[CustomBinary('llvm-compiler-rt', [
        'linux-x86_64',
        'linux-aarch64',
    ])]
    public function downBinary(ArtifactDownloader $downloader): DownloadResult
    {
        $llvmVersion = $this->detectZigLlvmVersion()
            ?? throw new DownloaderException('Could not detect bundled clang version from zig cc --version; ensure zig is installed');
        $tarball = "compiler-rt-{$llvmVersion}.src.tar.xz";
        $url = "https://github.com/llvm/llvm-project/releases/download/llvmorg-{$llvmVersion}/{$tarball}";
        $tarballPath = DOWNLOAD_PATH . '/' . $tarball;
        default_shell()->executeCurlDownload($url, $tarballPath, retries: $downloader->getRetry());
        return DownloadResult::archive($tarball, ['url' => $url, 'version' => $llvmVersion], extract: '{pkg_root_path}/llvm-compiler-rt', verified: false, version: $llvmVersion);
    }

    #[CustomBinaryCheckUpdate('llvm-compiler-rt', [
        'linux-x86_64',
        'linux-aarch64',
    ])]
    public function checkUpdateBinary(?string $old_version, ArtifactDownloader $downloader): CheckUpdateResult
    {
        $llvmVersion = $this->detectZigLlvmVersion()
            ?? throw new DownloaderException('Could not detect bundled clang version from zig cc --version; ensure zig is installed');
        return new CheckUpdateResult(
            old: $old_version,
            new: $llvmVersion,
            needUpdate: $old_version === null || $llvmVersion !== $old_version,
        );
    }

    #[AfterBinaryExtract('llvm-compiler-rt', [
        'linux-x86_64',
        'linux-aarch64',
    ])]
    public function postExtract(string $target_path): void
    {
        $this->buildForCurrentTarget($target_path);
    }

    public function buildForCurrentTarget(?string $sourceDir = null): bool
    {
        $sourceDir ??= PKG_ROOT_PATH . '/llvm-compiler-rt';
        $triple = SystemTarget::getCanonicalTriple();
        $libDir = PKG_ROOT_PATH . '/zig/lib/' . $triple;
        if ($this->isBuilt($libDir)) {
            return true;
        }
        if (!is_dir($sourceDir)) {
            logger()->warning("[llvm-compiler-rt] source dir missing at {$sourceDir}; cannot build runtime bits for {$triple}");
            return false;
        }
        $llvmVersion = $this->detectZigLlvmVersion();
        if ($llvmVersion === null) {
            logger()->warning('[llvm-compiler-rt] could not detect bundled clang version; skipping runtime bit build (PGO + shared libs without __dso_handle will fail to link)');
            return false;
        }
        logger()->info("Building llvm compiler-rt bits for {$triple} (LLVM {$llvmVersion})");

        $zig = PKG_ROOT_PATH . '/zig/zig';
        f_mkdir($libDir, recursive: true);
        $profileLib = "{$libDir}/libclang_rt.profile.a";
        $crtBegin = "{$libDir}/clang_rt.crtbegin.o";
        $crtEnd = "{$libDir}/clang_rt.crtend.o";
        if (!file_exists($profileLib)) {
            $this->buildProfileRuntime($zig, $sourceDir, $profileLib, $triple);
        }
        if (!file_exists($crtBegin) || !file_exists($crtEnd)) {
            $this->buildCrtObjects($zig, $sourceDir, $crtBegin, $crtEnd, $triple);
        }
        return $this->isBuilt($libDir);
    }

    public function isBuilt(string $libDir): bool
    {
        return file_exists("{$libDir}/libclang_rt.profile.a")
            && file_exists("{$libDir}/clang_rt.crtbegin.o")
            && file_exists("{$libDir}/clang_rt.crtend.o");
    }

    private function buildProfileRuntime(string $zig, string $srcRoot, string $libPath, string $triple): void
    {
        $profileSrc = "{$srcRoot}/lib/profile";
        $profileInc = "{$srcRoot}/include";
        if (!is_dir($profileSrc)) {
            logger()->warning("[llvm-compiler-rt] profile src dir missing at {$profileSrc} — PGO will not work");
            return;
        }
        $sources = array_merge(
            glob("{$profileSrc}/*.c") ?: [],
            glob("{$profileSrc}/*.cpp") ?: []
        );
        // Keep Linux-only compilation units; the others bring in OS-specific headers
        // we can't satisfy without their respective SDKs.
        $skip = ['/PlatformAIX', '/PlatformDarwin', '/PlatformFuchsia', '/PlatformOther', '/PlatformWindows', '/WindowsMMap'];
        $sources = array_filter($sources, function ($f) use ($skip) {
            foreach ($skip as $s) {
                if (str_contains($f, $s)) {
                    return false;
                }
            }
            return true;
        });

        $objDir = "{$srcRoot}/obj-profile-{$triple}";
        f_mkdir($objDir, recursive: true);
        $cflags = "-target {$triple} -c -O2 -fPIC -fvisibility=hidden " .
            '-I' . escapeshellarg($profileInc) . ' ' .
            '-DCOMPILER_RT_HAS_ATOMICS=1 -DCOMPILER_RT_HAS_FCNTL_LCK=1 -DCOMPILER_RT_HAS_UNAME=1';
        $objs = [];
        foreach ($sources as $src) {
            $obj = $objDir . '/' . pathinfo($src, PATHINFO_FILENAME) . '.o';
            $cmd = escapeshellarg($zig) . ' cc ' . $cflags . ' -o ' . escapeshellarg($obj) . ' ' . escapeshellarg($src) . ' 2>&1';
            if (!$this->runZigCmd($cmd, $obj, "failed to compile {$src}")) {
                return;
            }
            $objs[] = $obj;
        }
        $arCmd = escapeshellarg($zig) . ' ar rcs ' . escapeshellarg($libPath) . ' ' . implode(' ', array_map('escapeshellarg', $objs)) . ' 2>&1';
        if (!$this->runZigCmd($arCmd, $libPath, 'zig ar failed')) {
            return;
        }
        logger()->info('[llvm-compiler-rt] libclang_rt.profile.a installed (' . filesize($libPath) . ' bytes)');
    }

    private function buildCrtObjects(string $zig, string $srcRoot, string $crtBegin, string $crtEnd, string $triple): void
    {
        $beginSrc = "{$srcRoot}/lib/builtins/crtbegin.c";
        $endSrc = "{$srcRoot}/lib/builtins/crtend.c";
        if (!is_file($beginSrc) || !is_file($endSrc)) {
            logger()->error("[llvm-compiler-rt] crtbegin/crtend source missing under {$srcRoot}/lib/builtins — shared libs will lack __dso_handle");
            return;
        }
        $cflags = "-target {$triple} -c -O2 -fPIC -fvisibility=hidden -DCRT_HAS_INITFINI_ARRAY";
        foreach ([[$beginSrc, $crtBegin], [$endSrc, $crtEnd]] as [$src, $dst]) {
            $cmd = escapeshellarg($zig) . ' cc ' . $cflags . ' -o ' . escapeshellarg($dst) . ' ' . escapeshellarg($src) . ' 2>&1';
            if (!$this->runZigCmd($cmd, $dst, "failed to compile {$src}")) {
                return;
            }
        }
        logger()->info('[llvm-compiler-rt] clang_rt.crtbegin.o + clang_rt.crtend.o installed (' . filesize($crtBegin) . ' + ' . filesize($crtEnd) . ' bytes)');
    }

    private function runZigCmd(string $cmd, string $dst, string $errPrefix): bool
    {
        exec($cmd, $out, $rc);
        if ($rc !== 0 || !is_file($dst)) {
            logger()->warning("[llvm-compiler-rt] {$errPrefix}: " . implode("\n", $out));
            return false;
        }
        return true;
    }

    private function detectZigLlvmVersion(): ?string
    {
        $zig = PKG_ROOT_PATH . '/zig/zig';
        if (!is_file($zig)) {
            return null;
        }
        $verLine = trim((string) shell_exec(escapeshellarg($zig) . ' cc --version 2>/dev/null'));
        if (!preg_match('/clang version (\d+\.\d+\.\d+)/', $verLine, $m)) {
            return null;
        }
        return $m[1];
    }
}
