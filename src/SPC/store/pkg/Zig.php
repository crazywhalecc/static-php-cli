<?php

declare(strict_types=1);

namespace SPC\store\pkg;

use SPC\exception\DownloaderException;
use SPC\exception\WrongUsageException;
use SPC\store\CurlHook;
use SPC\store\Downloader;
use SPC\store\FileSystem;
use SPC\store\LockFile;

class Zig extends CustomPackage
{
    public static function isInstalled(): bool
    {
        $path = self::getPath();
        $files = ['zig', 'zig-cc', 'zig-c++', 'zig-ar', 'zig-ld.lld', 'zig-ranlib', 'zig-objcopy'];
        foreach ($files as $file) {
            if (!file_exists("{$path}/{$file}")) {
                return false;
            }
        }
        return true;
    }

    public function getSupportName(): array
    {
        return [
            'zig-x86_64-linux',
            'zig-aarch64-linux',
            'zig-x86_64-macos',
            'zig-aarch64-macos',
            'zig-x86_64-win',
        ];
    }

    public function fetch(string $name, bool $force = false, ?array $config = null): void
    {
        $pkgroot = PKG_ROOT_PATH;
        $zig_exec = match (PHP_OS_FAMILY) {
            'Windows' => "{$pkgroot}/{$name}/zig.exe",
            default => "{$pkgroot}/{$name}/zig",
        };

        if ($force) {
            FileSystem::removeDir("{$pkgroot}/{$name}");
        }

        if (file_exists($zig_exec)) {
            return;
        }

        $parts = explode('-', $name);
        $arch = $parts[1];
        $os = $parts[2];

        $zig_arch = match ($arch) {
            'x86_64', 'aarch64' => $arch,
            default => throw new WrongUsageException('Unsupported architecture: ' . $arch),
        };

        $zig_os = match ($os) {
            'linux' => 'linux',
            'macos' => 'macos',
            'win' => 'windows',
            default => throw new WrongUsageException('Unsupported OS: ' . $os),
        };

        $index_json = json_decode(Downloader::curlExec('https://ziglang.org/download/index.json', hooks: [[CurlHook::class, 'setupGithubToken']]), true);

        $latest_version = null;
        foreach ($index_json as $version => $data) {
            // Skip the master branch, get the latest stable release
            if ($version !== 'master') {
                $latest_version = $version;
                break;
            }
        }

        if (!$latest_version) {
            throw new DownloaderException('Could not determine latest Zig version');
        }

        logger()->info("Installing Zig version {$latest_version}");

        $platform_key = "{$zig_arch}-{$zig_os}";
        if (!isset($index_json[$latest_version][$platform_key])) {
            throw new DownloaderException("No download available for {$platform_key} in Zig version {$latest_version}");
        }

        $download_info = $index_json[$latest_version][$platform_key];
        $url = $download_info['tarball'];
        $filename = basename($url);

        $pkg = [
            'type' => 'url',
            'url' => $url,
            'filename' => $filename,
        ];

        Downloader::downloadPackage($name, $pkg, $force);
    }

    public function extract(string $name): void
    {
        $pkgroot = PKG_ROOT_PATH;
        $zig_bin_dir = "{$pkgroot}/zig";

        $files = ['zig', 'zig-cc', 'zig-c++', 'zig-ar', 'zig-ld.lld', 'zig-ranlib', 'zig-objcopy'];
        $all_exist = true;
        foreach ($files as $file) {
            if (!file_exists("{$zig_bin_dir}/{$file}")) {
                $all_exist = false;
                break;
            }
        }
        if (!$all_exist) {
            $lock = json_decode(FileSystem::readFile(LockFile::LOCK_FILE), true);
            $source_type = $lock[$name]['source_type'];
            $filename = DOWNLOAD_PATH . '/' . ($lock[$name]['filename'] ?? $lock[$name]['dirname']);
            $extract = "{$pkgroot}/zig";

            FileSystem::extractPackage($name, $source_type, $filename, $extract);

            $this->createZigCcScript($zig_bin_dir);
        }
        $this->buildClangRuntimeBits($zig_bin_dir);
    }

    public static function getEnvironment(): array
    {
        return [];
    }

    public static function getPath(): ?string
    {
        return PKG_ROOT_PATH . '/zig';
    }

    /**
     * Build the bits of clang's runtime that zig 0.16 doesn't ship: the
     * profile runtime (so -fprofile-generate actually emits .profraw) and
     * crtbegin.o/crtend.o (so shared libraries get __dso_handle and the
     * __cxa_finalize atexit hook).
     *
     * Build from 2mb compiler-rt-<llvm>.src tar
     * to avoid downloading 2gb full prebuilt tarball.
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
        $verLine = trim((string)shell_exec(escapeshellarg($zig) . ' cc --version 2>/dev/null'));
        if (!preg_match('/clang version (\d+\.\d+\.\d+)/', $verLine, $m)) {
            logger()->warning('[zig] could not detect bundled clang version; skipping runtime bit build (--pgo + shared libs without __dso_handle)');
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
        $pkgName = "compiler-rt-{$llvmVersion}";
        $tarball = "compiler-rt-{$llvmVersion}.src.tar.xz";
        $url = "https://github.com/llvm/llvm-project/releases/download/llvmorg-{$llvmVersion}/{$tarball}";
        try {
            Downloader::downloadPackage($pkgName, [
                'type' => 'url',
                'url' => $url,
                'filename' => $tarball,
            ]);
        }
        catch (\Throwable $e) {
            logger()->warning("[zig] failed to download {$tarball}: {$e->getMessage()}");
            return null;
        }
        $srcRoot = PKG_ROOT_PATH . "/compiler-rt-src-{$llvmVersion}";
        FileSystem::removeDir($srcRoot);
        FileSystem::extractPackage($pkgName, SPC_SOURCE_ARCHIVE, DOWNLOAD_PATH . '/' . $tarball, $srcRoot);
        return $srcRoot;
    }

    private function buildProfileRuntime(string $zig, string $srcRoot, string $libPath): void
    {
        $profileSrc = "{$srcRoot}/lib/profile";
        $profileInc = "{$srcRoot}/include";
        if (!is_dir($profileSrc)) {
            logger()->warning("[zig] profile src dir missing at {$profileSrc} — --pgo will not work");
            return;
        }
        $sources = array_merge(
            glob("{$profileSrc}/*.c") ?: [],
            glob("{$profileSrc}/*.cpp") ?: []
        );
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

    private function createZigCcScript(string $bin_dir): void
    {
        $script_path = __DIR__ . '/../scripts/zig-cc.sh';
        $script_content = file_get_contents($script_path);

        file_put_contents("{$bin_dir}/zig-cc", $script_content);
        chmod("{$bin_dir}/zig-cc", 0755);

        $script_content = str_replace('zig cc', 'zig c++', $script_content);
        file_put_contents("{$bin_dir}/zig-c++", $script_content);
        file_put_contents("{$bin_dir}/zig-ar", "#!/usr/bin/env bash\nexec zig ar $@");
        file_put_contents("{$bin_dir}/zig-ld.lld", "#!/usr/bin/env bash\nexec zig ld.lld $@");
        file_put_contents("{$bin_dir}/zig-ranlib", "#!/usr/bin/env bash\nexec zig ranlib $@");
        file_put_contents("{$bin_dir}/zig-objcopy", "#!/usr/bin/env bash\nexec zig objcopy $@");
        chmod("{$bin_dir}/zig-c++", 0755);
        chmod("{$bin_dir}/zig-ar", 0755);
        chmod("{$bin_dir}/zig-ld.lld", 0755);
        chmod("{$bin_dir}/zig-ranlib", 0755);
        chmod("{$bin_dir}/zig-objcopy", 0755);
    }
}
