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
use StaticPHP\Util\FileSystem;

class zig
{
    #[CustomBinary('zig', [
        'linux-x86_64',
        'linux-aarch64',
        'macos-x86_64',
        'macos-aarch64',
    ])]
    public function downBinary(ArtifactDownloader $downloader): DownloadResult
    {
        $index_json = default_shell()->executeCurl('https://ziglang.org/download/index.json', retries: $downloader->getRetry());
        $index_json = json_decode($index_json ?: '', true);
        $latest_version = null;
        if ($index_json === null) {
            throw new DownloaderException('Failed to fetch Zig version index');
        }
        foreach ($index_json as $version => $data) {
            if ($version !== 'master') {
                $latest_version = $version;
                break;
            }
        }

        if (!$latest_version) {
            throw new DownloaderException('Could not determine latest Zig version');
        }
        $zig_arch = SystemTarget::getTargetArch();
        $zig_os = match (SystemTarget::getTargetOS()) {
            'Windows' => 'win',
            'Darwin' => 'macos',
            'Linux' => 'linux',
            default => throw new DownloaderException('Unsupported OS for Zig: ' . SystemTarget::getTargetOS()),
        };
        $platform_key = "{$zig_arch}-{$zig_os}";
        if (!isset($index_json[$latest_version][$platform_key])) {
            throw new DownloaderException("No download available for {$platform_key} in Zig version {$latest_version}");
        }
        $download_info = $index_json[$latest_version][$platform_key];
        $url = $download_info['tarball'];
        $sha256 = $download_info['shasum'];
        $filename = basename($url);
        $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
        default_shell()->executeCurlDownload($url, $path, retries: $downloader->getRetry());
        // verify hash
        $file_hash = hash_file('sha256', $path);
        if ($file_hash !== $sha256) {
            throw new DownloaderException("Hash mismatch for downloaded Zig binary. Expected {$sha256}, got {$file_hash}");
        }
        return DownloadResult::archive(basename($path), ['url' => $url, 'version' => $latest_version], extract: '{pkg_root_path}/zig', verified: true, version: $latest_version);
    }

    #[CustomBinaryCheckUpdate('zig', [
        'linux-x86_64',
        'linux-aarch64',
        'macos-x86_64',
        'macos-aarch64',
    ])]
    public function checkUpdateBinary(?string $old_version, ArtifactDownloader $downloader): CheckUpdateResult
    {
        $index_json = default_shell()->executeCurl('https://ziglang.org/download/index.json', retries: $downloader->getRetry());
        $index_json = json_decode($index_json ?: '', true);
        $latest_version = null;
        if (!is_array($index_json)) {
            throw new DownloaderException('Failed to fetch Zig version index for update check');
        }
        foreach ($index_json as $version => $data) {
            if ($version !== 'master') {
                $latest_version = $version;
                break;
            }
        }
        if (!$latest_version) {
            throw new DownloaderException('Could not determine latest Zig version');
        }
        return new CheckUpdateResult(
            old: $old_version,
            new: $latest_version,
            needUpdate: $old_version === null || $latest_version !== $old_version,
        );
    }

    #[AfterBinaryExtract('zig', [
        'linux-x86_64',
        'linux-aarch64',
        'macos-x86_64',
        'macos-aarch64',
    ])]
    public function postExtractZig(string $target_path): void
    {
        $files = ['zig', 'zig-cc', 'zig-c++', 'zig-ar', 'zig-ld.lld', 'zig-ranlib', 'zig-objcopy'];
        $all_exist = true;
        foreach ($files as $file) {
            if (!file_exists("{$target_path}/{$file}")) {
                $all_exist = false;
                break;
            }
        }
        if ($all_exist) {
            return;
        }

        $script_path = ROOT_DIR . '/src/globals/scripts/zig-cc.sh';
        $script_content = file_get_contents($script_path);

        file_put_contents("{$target_path}/zig-cc", $script_content);
        chmod("{$target_path}/zig-cc", 0755);

        $script_content = str_replace('zig cc', 'zig c++', $script_content);
        file_put_contents("{$target_path}/zig-c++", $script_content);
        file_put_contents("{$target_path}/zig-ar", "#!/usr/bin/env bash\nexec zig ar $@");
        file_put_contents("{$target_path}/zig-ld.lld", "#!/usr/bin/env bash\nexec zig ld.lld $@");
        file_put_contents("{$target_path}/zig-ranlib", "#!/usr/bin/env bash\nexec zig ranlib $@");
        file_put_contents("{$target_path}/zig-objcopy", "#!/usr/bin/env bash\nexec zig objcopy $@");
        chmod("{$target_path}/zig-c++", 0755);
        chmod("{$target_path}/zig-ar", 0755);
        chmod("{$target_path}/zig-ld.lld", 0755);
        chmod("{$target_path}/zig-ranlib", 0755);
        chmod("{$target_path}/zig-objcopy", 0755);

        // Build the clang runtime bits zig 0.15+ doesn't ship: profile runtime
        // (so -fprofile-generate actually emits .profraw) and crtbegin/crtend
        // (so shared libs get __dso_handle and the __cxa_finalize atexit hook).
        // These get auto-linked by the zig-cc wrapper when the right flags fly past.
        $this->buildClangRuntimeBits($target_path);
    }

    /**
     * Detect the bundled clang version and build the missing clang runtime
     * archives into `<zig>/lib/`. Compiles from a 2 MB compiler-rt-<llvm>.src
     * tarball — far cheaper than fetching the 2 GB prebuilt LLVM tarball.
     */
    private function buildClangRuntimeBits(string $zig_bin_dir): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return;
        }
        $libDir = "{$zig_bin_dir}/lib";
        $profileLib = "{$libDir}/libclang_rt.profile.a";
        $crtBegin = "{$libDir}/clang_rt.crtbegin.o";
        $crtEnd = "{$libDir}/clang_rt.crtend.o";
        if (file_exists($profileLib) && file_exists($crtBegin) && file_exists($crtEnd)) {
            return;
        }

        $zig = "{$zig_bin_dir}/zig";
        $verLine = trim((string) shell_exec(escapeshellarg($zig) . ' cc --version 2>/dev/null'));
        if (!preg_match('/clang version (\d+\.\d+\.\d+)/', $verLine, $m)) {
            logger()->warning('[zig] could not detect bundled clang version; skipping runtime bit build (PGO + shared libs without __dso_handle will fail to link)');
            return;
        }
        $llvmVersion = $m[1];
        logger()->info("Building clang runtime bits for LLVM {$llvmVersion} (zig's bundled clang)");

        $srcRoot = $this->fetchCompilerRtSource($llvmVersion);
        if ($srcRoot === null) {
            return;
        }

        f_mkdir($libDir, recursive: true);
        if (!file_exists($profileLib)) {
            $this->buildProfileRuntime($zig, $srcRoot, $profileLib);
        }
        if (!file_exists($crtBegin) || !file_exists($crtEnd)) {
            $this->buildCrtObjects($zig, $srcRoot, $crtBegin, $crtEnd);
        }
        FileSystem::removeDir($srcRoot);
    }

    private function fetchCompilerRtSource(string $llvmVersion): ?string
    {
        $tarball = "compiler-rt-{$llvmVersion}.src.tar.xz";
        $url = "https://github.com/llvm/llvm-project/releases/download/llvmorg-{$llvmVersion}/{$tarball}";
        $tarballPath = DOWNLOAD_PATH . '/' . $tarball;
        if (!file_exists($tarballPath)) {
            try {
                default_shell()->executeCurlDownload($url, $tarballPath);
            } catch (\Throwable $e) {
                logger()->warning("[zig] failed to download {$tarball}: {$e->getMessage()}");
                return null;
            }
        }
        $srcRoot = PKG_ROOT_PATH . "/compiler-rt-src-{$llvmVersion}";
        FileSystem::removeDir($srcRoot);
        FileSystem::createDir($srcRoot);
        try {
            default_shell()->executeTarExtract($tarballPath, $srcRoot, 'xz');
        } catch (\Throwable $e) {
            logger()->warning("[zig] failed to extract {$tarball}: {$e->getMessage()}");
            return null;
        }
        return $srcRoot;
    }

    private function buildProfileRuntime(string $zig, string $srcRoot, string $libPath): void
    {
        $profileSrc = "{$srcRoot}/lib/profile";
        $profileInc = "{$srcRoot}/include";
        if (!is_dir($profileSrc)) {
            logger()->warning("[zig] profile src dir missing at {$profileSrc} — PGO will not work");
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

        $objDir = "{$srcRoot}/obj-profile";
        f_mkdir($objDir, recursive: true);
        $cflags = '-c -O2 -fPIC -fvisibility=hidden ' .
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
        logger()->info('[zig] libclang_rt.profile.a installed (' . filesize($libPath) . ' bytes)');
    }

    private function buildCrtObjects(string $zig, string $srcRoot, string $crtBegin, string $crtEnd): void
    {
        $beginSrc = "{$srcRoot}/lib/builtins/crtbegin.c";
        $endSrc = "{$srcRoot}/lib/builtins/crtend.c";
        if (!is_file($beginSrc) || !is_file($endSrc)) {
            logger()->error("[zig] crtbegin/crtend source missing under {$srcRoot}/lib/builtins — shared libs will lack __dso_handle");
            return;
        }
        $cflags = '-c -O2 -fPIC -fvisibility=hidden -DCRT_HAS_INITFINI_ARRAY';
        foreach ([[$beginSrc, $crtBegin], [$endSrc, $crtEnd]] as [$src, $dst]) {
            $cmd = escapeshellarg($zig) . ' cc ' . $cflags . ' -o ' . escapeshellarg($dst) . ' ' . escapeshellarg($src) . ' 2>&1';
            if (!$this->runZigCmd($cmd, $dst, "failed to compile {$src}")) {
                return;
            }
        }
        logger()->info('[zig] clang_rt.crtbegin.o + clang_rt.crtend.o installed (' . filesize($crtBegin) . ' + ' . filesize($crtEnd) . ' bytes)');
    }

    private function runZigCmd(string $cmd, string $dst, string $errPrefix): bool
    {
        exec($cmd, $out, $rc);
        if ($rc !== 0 || !is_file($dst)) {
            logger()->warning("[zig] {$errPrefix}: " . implode("\n", $out));
            return false;
        }
        return true;
    }
}
