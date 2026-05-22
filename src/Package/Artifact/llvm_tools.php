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
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\BuildFailureException;
use StaticPHP\Exception\DownloaderException;
use StaticPHP\Package\PackageBuilder;

class llvm_tools
{
    use GitHubTokenSetupTrait;

    public const array TOOLS = ['llvm-objcopy', 'llvm-strip', 'llvm-profdata'];

    #[CustomBinary('llvm-tools', [
        'linux-x86_64',
        'linux-aarch64',
        'macos-x86_64',
        'macos-aarch64',
    ])]
    public function downBinary(ArtifactDownloader $downloader): DownloadResult
    {
        $llvmVersion = $this->detectLlvmVersion()
            ?? throw new DownloaderException('Could not detect a clang version on host; install zig or clang first');
        $tarball = "llvm-project-{$llvmVersion}.src.tar.xz";
        $url = "https://github.com/llvm/llvm-project/releases/download/llvmorg-{$llvmVersion}/{$tarball}";
        $tarballPath = DOWNLOAD_PATH . '/' . $tarball;
        default_shell()->executeCurlDownload($url, $tarballPath, headers: $this->getGitHubTokenHeaders(), retries: $downloader->getRetry());
        return DownloadResult::archive($tarball, ['url' => $url, 'version' => $llvmVersion], extract: '{source_path}/llvm-tools', verified: false, version: $llvmVersion);
    }

    #[CustomBinaryCheckUpdate('llvm-tools', [
        'linux-x86_64',
        'linux-aarch64',
        'macos-x86_64',
        'macos-aarch64',
    ])]
    public function checkUpdateBinary(?string $old_version, ArtifactDownloader $downloader): CheckUpdateResult
    {
        $llvmVersion = $this->detectLlvmVersion()
            ?? throw new DownloaderException('Could not detect a clang version on host; install zig or clang first');
        return new CheckUpdateResult(
            old: $old_version,
            new: $llvmVersion,
            needUpdate: $old_version === null || $llvmVersion !== $old_version,
        );
    }

    #[AfterBinaryExtract('llvm-tools', [
        'linux-x86_64',
        'linux-aarch64',
        'macos-x86_64',
        'macos-aarch64',
    ])]
    public function postExtract(string $target_path): void
    {
        $this->buildForHost($target_path);
    }

    public function buildForHost(?string $sourceRoot = null): void
    {
        $sourceRoot ??= SOURCE_PATH . '/llvm-tools';
        $binDir = PKG_ROOT_PATH . '/llvm-tools/bin';
        if ($this->allBuilt($binDir)) {
            return;
        }
        $llvmDir = "{$sourceRoot}/llvm";
        if (!is_dir($llvmDir)) {
            throw new BuildFailureException("llvm-tools: missing source at {$llvmDir} (extraction layout changed?)");
        }
        $buildDir = "{$sourceRoot}/build";
        $installDir = PKG_ROOT_PATH . '/llvm-tools';
        f_mkdir($buildDir, recursive: true);
        f_mkdir($binDir, recursive: true);

        $cmakeArgs = implode(' ', array_map('escapeshellarg', [
            '-S', $llvmDir,
            '-B', $buildDir,
            '-DCMAKE_BUILD_TYPE=Release',
            '-DLLVM_ENABLE_PROJECTS=',
            '-DLLVM_TARGETS_TO_BUILD=',
            '-DLLVM_INCLUDE_BENCHMARKS=OFF',
            '-DLLVM_INCLUDE_TESTS=OFF',
            '-DLLVM_INCLUDE_EXAMPLES=OFF',
            '-DLLVM_INCLUDE_DOCS=OFF',
            '-DLLVM_ENABLE_ZLIB=OFF',
            '-DLLVM_ENABLE_ZSTD=OFF',
            '-DLLVM_ENABLE_LIBXML2=OFF',
            '-DLLVM_ENABLE_TERMINFO=OFF',
            '-DLLVM_ENABLE_LIBEDIT=OFF',
            '-DLLVM_ENABLE_LIBPFM=OFF',
            '-DLLVM_BUILD_LLVM_DYLIB=OFF',
            '-DLLVM_LINK_LLVM_DYLIB=OFF',
            '-DBUILD_SHARED_LIBS=OFF',
            '-DCMAKE_C_COMPILER=' . PKG_ROOT_PATH . '/zig/zig-cc',
            '-DCMAKE_CXX_COMPILER=' . PKG_ROOT_PATH . '/zig/zig-c++',
            '-DCMAKE_INSTALL_PREFIX=' . $installDir,
        ]));
        $jobs = ApplicationContext::get(PackageBuilder::class)->concurrency;
        $targetArgs = implode(' ', array_map(fn ($t) => '--target ' . escapeshellarg($t), self::TOOLS));

        shell()
            ->setEnv(['SPC_TARGET' => GNU_ARCH . '-linux-musl'])
            ->exec('cmake ' . $cmakeArgs)
            ->exec('cmake --build ' . escapeshellarg($buildDir) . ' ' . $targetArgs . " -j {$jobs}");

        foreach (self::TOOLS as $t) {
            $built = "{$buildDir}/bin/{$t}";
            if (!is_file($built)) {
                throw new BuildFailureException("llvm-tools: missing build output {$built}");
            }
            copy($built, "{$binDir}/{$t}");
            chmod("{$binDir}/{$t}", 0755);
        }
    }

    public function allBuilt(string $binDir): bool
    {
        foreach (self::TOOLS as $t) {
            $p = "{$binDir}/{$t}";
            if (!is_file($p) || !is_executable($p)) {
                return false;
            }
        }
        return true;
    }

    private function detectLlvmVersion(): ?string
    {
        $zig = PKG_ROOT_PATH . '/zig/zig';
        if (!is_file($zig)) {
            return null;
        }
        [$rc, $out] = shell()->execWithResult(escapeshellarg($zig) . ' cc --version', false);
        if ($rc !== 0) {
            return null;
        }
        return preg_match('/clang version (\d+\.\d+\.\d+)/', implode("\n", $out), $m) ? $m[1] : null;
    }
}
